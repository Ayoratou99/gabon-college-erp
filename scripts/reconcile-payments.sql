-- Réconciliation eBilling ↔ application
-- Usage (prod) :  psql "$DATABASE_URL" -f scripts/reconcile-payments.sql
--
-- L'export "candidats" ne contient AUCUN montant : il liste un candidat par
-- ligne. Un écart entre eBilling et l'application vient donc presque toujours
-- de la table `payments`, pas de la liste des candidats. Ces requêtes isolent
-- chaque cause possible, de la plus probable à la plus rare.

\echo '=== 1. TOTAL BRUT ENCAISSÉ (hors candidat de test, hors dossiers supprimés) ==='
SELECT COUNT(*)          AS paiements_confirmes,
       COUNT(DISTINCT p.candidat_id) AS candidats_distincts,
       SUM(p.amount)      AS brut_fcfa,
       ROUND(SUM(p.amount) * 0.025)             AS frais_ebilling_2_5pct,
       SUM(p.amount) - ROUND(SUM(p.amount) * 0.025) AS net_cuk
FROM payments p
JOIN candidats c ON c.id = p.candidat_id
WHERE p.status = 'PAID'
  AND c.deleted_at IS NULL
  AND c.is_test = false;

\echo ''
\echo '=== 2. DOUBLE PAIEMENT : candidats avec PLUSIEURS paiements confirmés ==='
\echo '    (cause n°1 d''un écart : le candidat a payé 2 fois, eBilling a encaissé 2 fois)'
SELECT c.matricule_public, c.nom, c.prenom, c.email,
       COUNT(*)      AS nb_paiements_payes,
       SUM(p.amount) AS total_paye,
       MIN(p.paid_at) AS premier, MAX(p.paid_at) AS dernier
FROM payments p
JOIN candidats c ON c.id = p.candidat_id
WHERE p.status = 'PAID' AND c.deleted_at IS NULL
GROUP BY c.id, c.matricule_public, c.nom, c.prenom, c.email
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC, total_paye DESC;

\echo ''
\echo '=== 3. RÉPARTITION DES MONTANTS (repère les montants anormaux) ==='
SELECT p.amount, COUNT(*) AS nb, SUM(p.amount) AS sous_total
FROM payments p
JOIN candidats c ON c.id = p.candidat_id
WHERE p.status = 'PAID' AND c.deleted_at IS NULL
GROUP BY p.amount
ORDER BY nb DESC;

\echo ''
\echo '=== 4. PAIEMENTS ORPHELINS : dossier supprimé (fusion de doublons) ==='
\echo '    Encaissés chez eBilling mais rattachés à un dossier soft-deleted.'
SELECT p.id, p.amount, p.paid_at, p.ebilling_id,
       c.matricule_public, c.nom, c.prenom, c.deleted_at
FROM payments p
JOIN candidats c ON c.id = p.candidat_id
WHERE p.status = 'PAID' AND c.deleted_at IS NOT NULL
ORDER BY p.paid_at DESC;

\echo ''
\echo '=== 5. CANDIDAT DE TEST (montant réduit, ne doit pas compter dans le CA) ==='
SELECT c.matricule_public, c.nom, c.prenom, c.email, p.amount, p.paid_at
FROM payments p
JOIN candidats c ON c.id = p.candidat_id
WHERE p.status = 'PAID' AND c.is_test = true;

\echo ''
\echo '=== 6. STATUT INCOHÉRENT : payé mais dossier non "valid"/"admis" ==='
SELECT c.matricule_public, c.nom, c.prenom, c.statut, p.amount, p.paid_at
FROM payments p
JOIN candidats c ON c.id = p.candidat_id
WHERE p.status = 'PAID'
  AND c.deleted_at IS NULL
  AND c.statut NOT IN ('valid', 'admis')
ORDER BY p.paid_at DESC;

\echo ''
\echo '=== 7. INVERSE : dossier "valid" SANS aucun paiement confirmé ==='
\echo '    (validé à la main ? import legacy ? paiement encaissé mais callback perdu ?)'
SELECT c.matricule_public, c.nom, c.prenom, c.email, c.valide_at
FROM candidats c
WHERE c.deleted_at IS NULL
  AND c.is_test = false
  AND c.statut IN ('valid', 'admis')
  AND NOT EXISTS (
      SELECT 1 FROM payments p
      WHERE p.candidat_id = c.id AND p.status = 'PAID'
  )
ORDER BY c.valide_at DESC;
