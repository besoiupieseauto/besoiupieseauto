<table class="table table-bordered">
    <thead>
        <tr class="info">
            <th>Client</th>
            <th>Adresa</th>
            <th>Telefon</th>
            <th>Marca</th>
            <th>Serie Sasiu</th>
            <th>Nr. inmat.</th>
            <th>Actiune</th>
        </tr>
    </thead>
    <tbody>
        @foreach($clients as $client)
        <tr>
            <td>{{ $client->nume }}</td>
            <td>{{ $client->adresa }}</td>
            <td>{{ $client->telefon }}</td>
            <td>{{ $client->marca }}</td>
            <td>{{ $client->sasiu }}</td>
            <td>{{ $client->nr_inmat }}</td>
            <td class="text-center" style="display: flex; justify-content: space-around;">
                <button class="btn btn-default" data-toggle="modal" data-target="#clientModal" onclick="editClient({{ $client->idclienti }})">
                    <i class="glyphicon glyphicon-edit"></i>
                </button>
                <form action="{{ route('clients.destroy', $client->idclienti) }}" method="POST" class="d-inline" onsubmit="return confirm('Esti sigur ca vrei sa stergi acest client?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-default btn-danger border">
                        <i class="fa fa-trash" aria-hidden="true"></i>
                    </button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<!-- Pagination Links -->
<div class="d-flex justify-content-center">
    <ul class="pagination">
        {{-- Previous Page Link --}}
        @if ($clients->onFirstPage())
        <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
        @else
        <li class="page-item"><a class="page-link" href="{{ $clients->previousPageUrl() }}">&laquo;</a></li>
        @endif

        {{-- Show first page link if not near the beginning --}}
        @if ($clients->currentPage() > 3)
        <li class="page-item"><a class="page-link" href="{{ $clients->url(1) }}">1</a></li>
        @if ($clients->currentPage() > 4)
        <li class="page-item disabled"><span class="page-link">...</span></li>
        @endif
        @endif

        {{-- Pagination Elements --}}
        @foreach(range(max(1, $clients->currentPage() - 2), min($clients->lastPage(), $clients->currentPage() + 2)) as $page)
            @if ($page == $clients->currentPage())
                <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $clients->url($page) }}">{{ $page }}</a></li>
            @endif
        @endforeach

        {{-- Show last page link if not near the end --}}
        @if ($clients->currentPage() < $clients->lastPage() - 2)
            @if ($clients->currentPage() < $clients->lastPage() - 3)
                <li class="page-item disabled"><span class="page-link">...</span></li>
            @endif
            <li class="page-item"><a class="page-link" href="{{ $clients->url($clients->lastPage()) }}">{{ $clients->lastPage() }}</a></li>
        @endif

        {{-- Next Page Link --}}
        @if ($clients->hasMorePages())
        <li class="page-item"><a class="page-link" href="{{ $clients->nextPageUrl() }}">&raquo;</a></li>
        @else
        <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
        @endif
    </ul>
</div>