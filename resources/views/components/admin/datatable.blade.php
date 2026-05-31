@props([
    'id',
    'headings' => [],   // list of strings; last empty col = actions
    'class'    => '',
])

<table id="{{ $id }}" class="table table-hover dt-cuk align-middle {{ $class }}" style="width:100%">
    <thead>
        <tr>
            @foreach ($headings as $h)
                <th>{!! $h !!}</th>
            @endforeach
        </tr>
    </thead>
    <tbody></tbody>
</table>
