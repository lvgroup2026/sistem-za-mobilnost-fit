<x-app-layout>
  @php
    $ocjenaSlovom = fn ($ocjena) => match ((int) $ocjena) {
        10 => 'A', 9 => 'B', 8 => 'C', 7 => 'D', 6 => 'E', 5 => 'F', default => null,
    };
    $bojaOcjene = fn ($ocjena) => match (true) {
        $ocjena >= 9 => 'bg-green-100 text-green-800',
        $ocjena >= 7 => 'bg-blue-100 text-blue-800',
        $ocjena >= 6 => 'bg-yellow-100 text-yellow-800',
        default => 'bg-gray-100 text-gray-600',
    };
    $statusZahtjeva = [
        'pending' => ['U obradi', 'bg-yellow-100 text-yellow-800'],
        'accepted' => ['Prihvaćen', 'bg-green-100 text-green-800'],
        'rejected' => ['Odbijen', 'bg-red-100 text-red-800'],
        'completed' => ['Završen', 'bg-blue-100 text-blue-800'],
    ];
    $maticni = $student->fakulteti->first();
  @endphp

  <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6">
    <a href="{{ route('students.index') }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800 mb-4">
      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Nazad na listu studenata
    </a>

    <!-- Zaglavlje -->
    <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-6 mb-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center">
          <div class="flex-shrink-0 h-14 w-14 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xl font-bold">
            {{ substr($student->ime, 0, 1) }}{{ substr($student->prezime, 0, 1) }}
          </div>
          <div class="ml-4">
            <h1 class="text-2xl font-bold text-gray-900">{{ $student->ime }} {{ $student->prezime }}</h1>
            <div class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1 mt-1">
              <span>Indeks: <span class="font-medium text-gray-700">{{ $student->br_indexa }}</span></span>
              <span>Fakultet: <span class="font-medium text-gray-700">{{ $maticni->naziv ?? '-' }}</span></span>
              <span>{{ $student->nivoStudija->naziv ?? '-' }} studije, {{ $student->godina_studija }}. godina</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $student->status === 'prepis' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
            {{ $student->status === 'prepis' ? 'Prepis' : 'Mobilnost' }}
          </span>
          <a href="{{ route('students.edit', $student->id) }}" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded-md text-sm font-medium transition-colors">Izmijeni</a>
          <a href="{{ route('students.documents.index', $student->id) }}" class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md text-sm font-medium transition-colors">Dokumenta</a>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
        <div class="rounded-lg bg-gray-50 border border-gray-100 p-4">
          <div class="text-xs uppercase tracking-wider text-gray-500">Položeni ispiti</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">{{ $ukupno['polozeno'] }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 border border-gray-100 p-4">
          <div class="text-xs uppercase tracking-wider text-gray-500">Ukupno ECTS</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">{{ $ukupno['ects'] }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 border border-gray-100 p-4">
          <div class="text-xs uppercase tracking-wider text-gray-500">Prosječna ocjena</div>
          <div class="text-2xl font-bold text-gray-900 mt-1">{{ $ukupno['prosjek'] ?? '-' }}</div>
        </div>
      </div>
    </div>

    <!-- Ispiti po fakultetima -->
    <h2 class="text-lg font-semibold text-gray-800 mb-3">Ispiti po fakultetima</h2>

    @forelse($poFakultetima as $nazivFakulteta => $grupa)
      <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div class="flex items-center">
            <h3 class="text-base font-semibold text-gray-800">{{ $nazivFakulteta }}</h3>
            @if($maticni && $nazivFakulteta === $maticni->naziv)
              <span class="ml-2 text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">matični</span>
            @endif
          </div>
          <div class="flex flex-wrap gap-2 text-xs">
            <span class="bg-white border border-gray-200 text-gray-700 px-2.5 py-0.5 rounded-full">Položeno: {{ $grupa['polozeno'] }}/{{ $grupa['predmeti']->count() }}</span>
            <span class="bg-white border border-gray-200 text-gray-700 px-2.5 py-0.5 rounded-full">ECTS: {{ $grupa['ects'] }}</span>
            <span class="bg-white border border-gray-200 text-gray-700 px-2.5 py-0.5 rounded-full">Prosjek: {{ $grupa['prosjek'] ?? '-' }}</span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-white">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Predmet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Semestar</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">ECTS</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ocjena</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach($grupa['predmeti'] as $predmet)
                @php $ocjena = $predmet->pivot->grade; @endphp
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-3 text-sm text-gray-900">
                    <div class="font-medium">{{ $predmet->naziv }}</div>
                    @if(isset($priznatiNaOsnovu[$predmet->id]))
                      <div class="text-xs text-green-700 mt-0.5">Priznat prepisom: {{ implode(', ', $priznatiNaOsnovu[$predmet->id]) }}</div>
                    @endif
                  </td>
                  <td class="px-6 py-3 text-sm text-gray-500 hidden sm:table-cell">{{ $predmet->semestar ?? '-' }}</td>
                  <td class="px-6 py-3 text-sm text-gray-500 hidden sm:table-cell">{{ $predmet->ects ?? '-' }}</td>
                  <td class="px-6 py-3 text-sm">
                    @if($ocjena)
                      <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $bojaOcjene($ocjena) }}">
                        {{ $ocjenaSlovom($ocjena) }} ({{ $ocjena }})
                      </span>
                    @else
                      <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">Bez ocjene</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @empty
      <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-6 text-center text-gray-500 mb-6">
        Student nema unesenih ispita.
      </div>
    @endforelse

    <!-- Zahtjevi za prepis -->
    @if($student->mappingRequests->isNotEmpty())
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Zahtjevi za prepis</h2>
      <div class="bg-white shadow-sm rounded-xl border border-gray-200 divide-y divide-gray-100">
        @foreach($student->mappingRequests->sortByDesc('created_at') as $zahtjev)
          @php [$tekst, $boja] = $statusZahtjeva[$zahtjev->status] ?? [$zahtjev->status, 'bg-gray-100 text-gray-700']; @endphp
          <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
              <div class="text-sm font-medium text-gray-900">{{ $zahtjev->fakultet->naziv ?? '-' }}</div>
              <div class="text-xs text-gray-500">
                {{ $zahtjev->subjects->count() }} ispita, kreiran {{ $zahtjev->created_at?->format('d.m.Y') }}
              </div>
            </div>
            <div class="flex items-center gap-3">
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $boja }}">{{ $tekst }}</span>
              <a href="{{ route('prepis.mapping-request.show', $zahtjev->id) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Otvori</a>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</x-app-layout>
