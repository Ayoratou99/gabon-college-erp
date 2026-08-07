-- Réconciliation eBilling ↔ application — TOUT EN UNE SEULE REQUÊTE.
-- Lecture seule : uniquement des SELECT, aucune écriture.
-- Coller tel quel dans la console pgAdmin puis exécuter (F5).
--
-- Le résultat arrive dans une seule grille, section par section.
-- La ligne "0. RÉSUMÉ" donne les compteurs : si un compteur vaut 0,
-- la section correspondante n'aura simplement aucune ligne détaillée.

WITH paid AS (
    SELECT p.candidat_id,
           p.amount,
           p.paid_at,
           p.ebilling_id,
           c.matricule_public,
           COALESCE(c.nom, '')    AS nom,
           COALESCE(c.prenom, '') AS prenom,
           COALESCE(c.email, '')  AS email,
           c.statut,
           c.is_test,
           c.deleted_at           AS c_deleted
    FROM payments p
    JOIN candidats c ON c.id = p.candidat_id
    WHERE p.status = 'PAID'
      AND p.deleted_at IS NULL          -- ignore les paiements supprimés
),
net AS (   -- périmètre "chiffre d'affaires réel"
    SELECT * FROM paid WHERE c_deleted IS NULL AND is_test = false
)

-- 0. RÉSUMÉ ------------------------------------------------------------
SELECT 0 AS ord,
       '0. RESUME' AS section,
       'doublons=' || (SELECT COUNT(*) FROM (
            SELECT 1 FROM paid WHERE c_deleted IS NULL
            GROUP BY candidat_id HAVING COUNT(*) > 1) d)::text AS ref,
       'orphelins=' || (SELECT COUNT(*) FROM paid WHERE c_deleted IS NOT NULL)::text AS info,
       'test=' || (SELECT COUNT(*) FROM paid WHERE is_test)::text AS nb,
       'statut_anormal=' || (SELECT COUNT(*) FROM paid
            WHERE c_deleted IS NULL AND statut NOT IN ('valid','admis'))::text AS montant

-- 1. TOTAUX ------------------------------------------------------------
UNION ALL
SELECT 1,
       '1. TOTAUX (hors test, hors supprimes)',
       'paiements=' || COUNT(*)::text,
       'candidats=' || COUNT(DISTINCT candidat_id)::text,
       'BRUT=' || COALESCE(SUM(amount), 0)::text,
       'frais2.5%=' || ROUND(COALESCE(SUM(amount), 0) * 0.025)::text
         || '  NET=' || (COALESCE(SUM(amount), 0) - ROUND(COALESCE(SUM(amount), 0) * 0.025))::text
FROM net

-- 2. DOUBLE PAIEMENT ---------------------------------------------------
UNION ALL
SELECT 2,
       '2. DOUBLE PAIEMENT',
       matricule_public,
       nom || ' ' || prenom || ' <' || email || '>',
       'nb_paiements=' || COUNT(*)::text,
       'total=' || SUM(amount)::text
FROM paid
WHERE c_deleted IS NULL
GROUP BY matricule_public, nom, prenom, email
HAVING COUNT(*) > 1

-- 3. RÉPARTITION DES MONTANTS -----------------------------------------
UNION ALL
SELECT 3,
       '3. REPARTITION MONTANTS',
       'montant=' || amount::text,
       '',
       'nb=' || COUNT(*)::text,
       'sous_total=' || SUM(amount)::text
FROM paid
WHERE c_deleted IS NULL
GROUP BY amount

-- 4. PAIEMENTS ORPHELINS (dossier supprimé) ----------------------------
UNION ALL
SELECT 4,
       '4. ORPHELIN (dossier supprime)',
       matricule_public,
       nom || ' ' || prenom || ' <' || email || '>',
       'paye_le=' || COALESCE(paid_at::text, '?'),
       'montant=' || amount::text
FROM paid
WHERE c_deleted IS NOT NULL

-- 5. CANDIDAT DE TEST --------------------------------------------------
UNION ALL
SELECT 5,
       '5. CANDIDAT DE TEST',
       matricule_public,
       nom || ' ' || prenom || ' <' || email || '>',
       'paye_le=' || COALESCE(paid_at::text, '?'),
       'montant=' || amount::text
FROM paid
WHERE is_test = true

-- 6. PAYÉ MAIS STATUT ANORMAL -----------------------------------------
UNION ALL
SELECT 6,
       '6. PAYE MAIS STATUT ANORMAL',
       matricule_public,
       nom || ' ' || prenom || ' <' || email || '>',
       'statut=' || COALESCE(statut, '?'),
       'montant=' || amount::text
FROM paid
WHERE c_deleted IS NULL
  AND statut NOT IN ('valid', 'admis')

ORDER BY ord, ref;
