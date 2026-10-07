<x-app-layout>

  @if(session('success'))
    <div class="mb-4 bg-green-100 text-green-800 p-3 rounded-md">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
      <span class="block">Došlo je do greške pri unosu:</span>
      <ul class="mt-2 list-disc list-inside">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="py-10 max-w-7xl mx-auto px-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-gray-900">Studenti</h1>
      <a href="{{ route('students.create') }}"
        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg shadow-lg transform transition hover:scale-105 flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Dodaj studenta
      </a>
    </div>

    <div class="mb-4 flex flex-col md:flex-row gap-4">
      <form action="{{ route('students.index') }}" method="GET" class="w-full max-w-md">
        <div class="relative">
          @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
          @endif
          <input type="text" name="search" id="searchStudent" value="{{ request('search') }}"
            placeholder="Pretraži studente po imenu, prezime, indeksu..."
            class="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 outline-none transition-all">
          <div class="absolute left-3 top-2.5 text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          @if(request('search'))
            <a href="{{ route('students.index', request()->only('status')) }}" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </a>
          @endif
        </div>
      </form>
      
      <form method="GET" action="{{ route('students.index') }}" class="w-full max-w-xs">
          @if(request('search'))
            <input type="hidden" name="search" value="{{ request('search') }}">
          @endif
          <select name="status" onchange="this.form.submit()" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-4 py-2">
              <option value="">Svi statusi</option>
              <option value="mobilnost" {{ request('status') == 'mobilnost' ? 'selected' : '' }}>Mobilnost</option>
              <option value="prepis" {{ request('status') == 'prepis' ? 'selected' : '' }}>Prepis</option>
          </select>
      </form>
    </div>

    <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Lista studenata</h2>
        @include('partials.platforma-legenda')
        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">{{ $students->total() }}
          Ukupno</span>
      </div>

      @if($students->count() > 0)
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="studentTable">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ime i Prezime
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Indeks</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Email</th>
               <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Godina</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Fakultet</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden xl:table-cell">Nivo</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Radnja</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200" id="studentTableBody">
            @foreach ($students as $student)
              <tr class="student-row hover:bg-gray-50 transition-colors duration-150 ease-in-out">

                <td class="px-6 py-4">
                  <div class="flex items-center">
                    <div
                      class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                      {{ substr($student->ime, 0, 1) }}{{ substr($student->prezime, 0, 1) }}
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900"><a href="{{ route('students.show', $student->id) }}" class="hover:text-indigo-600 hover:underline">{{ $student->ime }} {{ $student->prezime }}</a>
                        @if($student->platforma_student_id)
                          <span title="Povezan sa studentskom platformom (ID {{ $student->platforma_student_id }})" class="ml-1 inline-block w-2 h-2 rounded-full bg-green-500 align-middle"></span>
                        @endif
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $student->br_indexa }}</td>
                <td class="px-6 py-4 text-sm text-gray-500 hidden md:table-cell">{{ $student->email }}</td>
                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">{{ $student->godina_studija }}</td>
                <td class="px-6 py-4 text-sm text-gray-500 hidden lg:table-cell">
                  {{ $student->fakulteti->first()->naziv ?? 'N/A' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden xl:table-cell">{{ $student->nivoStudija->naziv ?? 'N/A' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                  <div class="flex justify-center space-x-2">
    <a href="{{ route('students.show', $student->id) }}"
      class="text-gray-700 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded-md transition-colors">
      Pregled
    </a>

    <a href="{{ route('students.edit', $student->id) }}"
      class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded-md transition-colors">
      Izmijeni
    </a>

    @if($student->platforma_student_id && config('platforma.enabled'))
    <form action="{{ route('platforma.studenti.osvjezi', $student->id) }}" method="POST">
      @csrf
      <button type="submit" title="Osvježi podatke sa studentske platforme"
        class="text-green-700 hover:text-green-900 bg-green-50 hover:bg-green-100 px-3 py-1 rounded-md transition-colors">
        Osvježi
      </button>
    </form>
    @endif

    <a href="{{ route('students.documents.index', $student->id) }}"
      class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md transition-colors">
      Dokumenta
    </a>

    <form action="{{ route('students.destroy', $student->id) }}" method="POST"
      onsubmit="return confirm('Jeste li sigurni da želite da obrišete studenta?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit"
                        class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md transition-colors">
                        Obriši
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if($students->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          {{ $students->links() }}
        </div>
      @endif
      @else
      <div class="p-6 text-center text-gray-500">
          Nema studenata za prikaz.
      </div>
      @endif
    </div>
  </div>

  <div id="studentModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 id="modalTitle" class="text-xl font-semibold mb-4">Dodaj studenta</h2>

      <form id="studentForm" action="{{ route('students.store') }}" method="POST">
        @csrf
        <input type="hidden" name="id" id="studentId">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="mb-4">
            <label for="ime" class="block text-gray-700 font-medium mb-1">Ime</label>
            <input type="text" id="ime" name="ime"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="prezime" class="block text-gray-700 font-medium mb-1">Prezime</label>
            <input type="text" id="prezime" name="prezime"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="br_indexa" class="block text-gray-700 font-medium mb-1">Broj Indeksa</label>
            <input type="text" id="br_indexa" name="br_indexa"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="datum_rodjenja" class="block text-gray-700 font-medium mb-1">Datum Rodjenja</label>
            <input type="date" id="datum_rodjenja" name="datum_rodjenja"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="telefon" class="block text-gray-700 font-medium mb-1">Telefon</label>
            <input type="text" id="telefon" name="telefon"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="email" class="block text-gray-700 font-medium mb-1">Email</label>
            <input type="email" id="email" name="email"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="godina_studija" class="block text-gray-700 font-medium mb-1">Godina Studija</label>
            <input type="number" id="godina_studija" name="godina_studija"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label for="jmbg" class="block text-gray-700 font-medium mb-1">JMBG</label>
            <input type="text" id="jmbg" name="jmbg"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
          </div>

          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Pol</label>
            <div class="flex items-center space-x-6">
              <label class="inline-flex items-center">
                <input type="radio" name="pol" id="pol_muski" value="musko" checked
                  class="form-radio text-blue-600" />
                <span class="ml-2 text-gray-700">Muški</span>
              </label>

              <label class="inline-flex items-center">
                <input type="radio" name="pol" id="pol_zenski" value="zensko"
                  class="form-radio text-blue-600" />
                <span class="ml-2 text-gray-700">Ženski</span>
              </label>
            </div>
          </div>

           <div class="mb-4">
            <label for="nivo_studija_id" class="block text-gray-700 font-medium mb-1">Nivo Studija</label>
            <select id="nivo_studija_id" name="nivo_studija_id"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
              <option value="">Odaberite nivo studija</option>
              @foreach($nivoStudija as $nivo)
                <option value="{{ $nivo->id }}">{{ $nivo->naziv }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-4">
            <label for="fakultet_id" class="block text-gray-700 font-medium mb-1">Fakultet</label>
            <select id="fakultet_id" name="fakultet_id"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
              <option value="">Odaberite fakultet</option>
              @foreach($fakulteti as $fakultet)
                <option value="{{ $fakultet->id }}">{{ $fakultet->naziv }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="flex justify-end space-x-2 mt-4">
          <button type="button" id="cancelModal" class="px-4 py-2 rounded-md border border-gray-300 hover:bg-gray-100 shadow-lg transform transition hover:scale-105">
            Cancel
          </button>
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-lg transform transition hover:scale-105">
            Save
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const modal = document.getElementById('studentModal');
    const addStudentBtn = document.getElementById('addStudentBtn');
    const cancelModal = document.getElementById('cancelModal');
    const form = document.getElementById('studentForm');
    const title = document.getElementById('modalTitle');

    addStudentBtn.addEventListener('click', () => {
      form.action = "{{ route('students.store') }}";
      form.reset();

      // Remove method spoofing if it exists
      const existingMethod = form.querySelector('input[name="_method"]');
      if (existingMethod) existingMethod.remove();

      title.textContent = 'Add Student';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });

    cancelModal.addEventListener('click', () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    });

    function openEditModal(student) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      title.textContent = 'Edit Student';
      form.action = `/admin/students/${student.id}`;

      // Add method spoofing for PUT
      const existingMethod = form.querySelector('input[name="_method"]');
      if (existingMethod) existingMethod.remove();

      const methodInput = document.createElement('input');
      methodInput.type = 'hidden';
      methodInput.name = '_method';
      methodInput.value = 'PUT';
      form.appendChild(methodInput);

      document.getElementById('studentId').value = student.id;
      document.getElementById('ime').value = student.ime;
      document.getElementById('prezime').value = student.prezime;
      document.getElementById('br_indexa').value = student.br_indexa;
      document.getElementById('datum_rodjenja').value = student.datum_rodjenja;
      document.getElementById('telefon').value = student.telefon;
      document.getElementById('email').value = student.email;
      document.getElementById('godina_studija').value = student.godina_studija;
      document.getElementById('jmbg').value = student.jmbg;
      document.getElementById('nivo_studija_id').value = student.nivo_studija_id;
      
      if (student.fakulteti && student.fakulteti.length > 0) {
        document.getElementById('fakultet_id').value = student.fakulteti[0].id;
      } else {
        document.getElementById('fakultet_id').value = '';
      }

      // Set pol radio (student.pol may be 0/1 or boolean)
      const polMuski = document.getElementById('pol_muski');
      const polZenski = document.getElementById('pol_zenski');
      if (student.pol === 'musko') {
        polMuski.checked = true;
      } else {
        polZenski.checked = true;
      }
    }
  </script>



</x-app-layout>