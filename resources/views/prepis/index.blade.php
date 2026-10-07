<x-app-layout>
    @if(session('success'))
        <div class="mb-4 bg-green-100 text-green-800 p-3 rounded-md">
            {{ session('success') }}
        </div>
    @endif

    @php
        $filteri = [
            'svi' => ['Svi zahtjevi', 'border-indigo-500 text-indigo-700', 'bg-indigo-100 text-indigo-800'],
            'u_obradi' => ['Čeka profesora', 'border-yellow-500 text-yellow-700', 'bg-yellow-100 text-yellow-800'],
            'spremno' => ['Spremno za reviziju', 'border-blue-500 text-blue-700', 'bg-blue-100 text-blue-800'],
            'prihvaceni' => ['Prihvaćeni', 'border-green-500 text-green-700', 'bg-green-100 text-green-800'],
            'odbijeni' => ['Odbijeni', 'border-red-500 text-red-700', 'bg-red-100 text-red-800'],
        ];
    @endphp

    <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Prepisi</h1>
            <div class="flex space-x-4">
                <a href="{{ route('prepis.match') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg shadow-lg transform transition hover:scale-105 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Dodaj prepis
                </a>
            </div>
        </div>

        <!-- Filter po statusu -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            @foreach($filteri as $kljuc => [$naziv, $aktivno, $znacka])
                <a href="{{ route('prepis.index', array_filter(['status' => $kljuc === 'svi' ? null : $kljuc, 'search' => request('search')])) }}"
                   class="bg-white rounded-xl border-2 px-4 py-3 shadow-sm hover:shadow transition {{ $aktivniStatus === $kljuc ? $aktivno : 'border-transparent text-gray-600 hover:border-gray-200' }}">
                    <div class="text-xs font-medium uppercase tracking-wider">{{ $naziv }}</div>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-2xl font-bold text-gray-900">{{ $brojPoStatusu[$kljuc] }}</span>
                        @if($aktivniStatus === $kljuc)
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $znacka }}">prikazano</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <!-- Search Form -->
        <div class="mb-4">
            <form action="{{ route('prepis.index') }}" method="GET" class="w-full max-w-md">
                @if($aktivniStatus !== 'svi')
                    <input type="hidden" name="status" value="{{ $aktivniStatus }}">
                @endif
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Pretraži prepise po studentu ili fakultetu..."
                        class="w-full pl-10 pr-10 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                    <div class="absolute left-3 top-2.5 text-blue-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('prepis.index', array_filter(['status' => $aktivniStatus === 'svi' ? null : $aktivniStatus])) }}" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Mapping Requests Table -->
        <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200">
             <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">{{ $filteri[$aktivniStatus][0] }}</h2>
                <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2.5 py-0.5 rounded-full">{{ $mappingRequests->total() }} Ukupno</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Prepis sa fakulteta</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ispiti po profesorima</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Akcije</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($mappingRequests as $request)
                            @php
                                $totalSubjects = $request->subjects->count();
                                $matchedSubjects = $request->subjects->whereNotNull('fit_predmet_id')->count();
                                $rejectedSubjects = $request->subjects->where('is_rejected', true)->count();
                                $processedSubjects = $matchedSubjects + $rejectedSubjects;
                                $allProcessed = ($totalSubjects > 0 && $processedSubjects == $totalSubjects);
                                $allRejected = ($totalSubjects > 0 && $rejectedSubjects == $totalSubjects);
                                $procenat = $totalSubjects > 0 ? round($processedSubjects / $totalSubjects * 100) : 0;

                                [$statusText, $color] = match($request->status) {
                                    'accepted' => ['Prihvaćen', 'bg-green-100 text-green-800'],
                                    'rejected' => ['Odbijen', 'bg-red-100 text-red-800'],
                                    default => $allProcessed
                                        ? ['Spremno za reviziju', 'bg-blue-100 text-blue-800']
                                        : ['Čeka profesora', 'bg-yellow-100 text-yellow-800'],
                                };

                                $poProfesorima = $request->subjects->groupBy(fn ($s) => $s->professor->name ?? 'Nije dodijeljen');
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out align-top">
                                <td class="px-6 py-4">
                                    @if($request->student)
                                        <a href="{{ route('students.show', $request->student->id) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600 hover:underline">
                                            {{ $request->student->ime }} {{ $request->student->prezime }}
                                        </a>
                                    @endif
                                    <div class="text-sm text-gray-500">Indeks: {{ $request->student->br_indexa ?? '-' }}</div>
                                    <div class="text-xs text-gray-400 mt-1">{{ $request->created_at?->format('d.m.Y') }}</div>
                                    <div class="text-xs text-gray-500 mt-1 md:hidden">{{ $request->fakultet->naziv ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">
                                    <span class="inline-flex items-center text-sm text-gray-800 bg-gray-100 px-2.5 py-1 rounded-md">
                                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        {{ $request->fakultet->naziv ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="space-y-3">
                                        @foreach($poProfesorima as $profesor => $ispiti)
                                            <div>
                                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ $profesor }}</div>
                                                <ul class="space-y-1">
                                                    @foreach($ispiti as $subject)
                                                        <li class="flex flex-wrap items-center gap-1">
                                                            <span class="text-gray-900">{{ $subject->straniPredmet->naziv }}</span>
                                                            @if($subject->fitPredmet)
                                                                <span class="text-gray-400">&rarr;</span>
                                                                <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-1.5 py-0.5 rounded">{{ $subject->fitPredmet->naziv }}</span>
                                                            @elseif($subject->is_rejected)
                                                                <span class="text-xs bg-red-50 text-red-700 border border-red-200 px-1.5 py-0.5 rounded">odbijen</span>
                                                            @else
                                                                <span class="text-xs bg-yellow-50 text-yellow-700 border border-yellow-200 px-1.5 py-0.5 rounded">čeka odluku</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $color }}">
                                        {{ $statusText }}
                                    </span>
                                    @if($request->status == 'pending')
                                        <div class="mt-2 w-32">
                                            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-1.5 rounded-full {{ $allRejected ? 'bg-red-500' : ($allProcessed ? 'bg-blue-500' : 'bg-yellow-400') }}" style="width: {{ $procenat }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">Obrađeno {{ $processedSubjects }}/{{ $totalSubjects }}</div>
                                        </div>
                                        @if($allRejected)
                                            <div class="text-xs text-red-600 mt-1 font-bold">Profesor je odbio sve</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex flex-col sm:flex-row justify-center gap-2">
                                        <a href="{{ route('prepis.mapping-request.show', $request->id) }}" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded-md transition-colors">
                                            Pregledaj zahtjev
                                        </a>
                                        <form action="{{ route('prepis.mapping-request.destroy', $request->id) }}" method="POST" onsubmit="return confirm('Jeste li sigurni da želite da izbrišete ovaj zahtjev?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md transition-colors">
                                                Izbriši
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 italic">
                                    Nema zahtjeva za prepis.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($mappingRequests->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    {{ $mappingRequests->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
