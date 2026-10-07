<x-app-layout>
  <div class="py-10 max-w-4xl mx-auto px-6">
    <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Izmijeni studenta</h1>
        <a href="{{ route('students.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold">
          &larr; Nazad
        </a>
      </div>

      <form @platforma-student-selected.window="platformaLinked = true" x-data="{
            platformaLinked: {{ $student->platforma_student_id ? 'true' : 'false' }},
            platformaFakulteti: {{ json_encode($fakulteti->map(fn($f) => ['id' => $f->id, 'platforma' => (bool) $f->platforma_fakultet_id])) }},
            get facultyLinked() { let f = this.platformaFakulteti.find(x => x.id == this.selectedFaculty); return !!(f && f.platforma); },
            get hideLocalSubjects() { return this.platformaLinked && this.facultyLinked; },
          selectedFaculty: '{{ old('fakultet_id', $student->fakulteti->first()->id ?? '') }}',
          studyYear: '{{ old('godina_studija', $student->godina_studija) }}',
          studyLevel: '{{ old('nivo_studija_id', $student->nivo_studija_id) }}',
          studyLevels: {{ json_encode($nivoStudija) }},
          checkYear() {
              if (parseInt(this.studyYear) > 3) {
                  let master = this.studyLevels.find(l => l.naziv === 'Master');
                  if (master) {
                      this.studyLevel = master.id;
                      this.$nextTick(() => {
                          window.dispatchEvent(new CustomEvent('study-level-changed', { detail: this.studyLevel }));
                      });
                  }
              }
          },
          async fetchSubjects() {
              if (!this.selectedFaculty) return;
              try {
                  let response = await fetch(`/admin/api/fakulteti/${this.selectedFaculty}/predmeti`);
                  if (!response.ok) throw new Error('Network response was not ok');
                  let subjects = await response.json();
                  window.dispatchEvent(new CustomEvent('update-subjects', { detail: subjects }));
              } catch (error) {
                  console.error('Error fetching subjects:', error);
                  alert('Failed to load subjects for the selected faculty.');
              }
          }
      }" action="{{ route('students.update', $student->id) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="tor_uvezen" id="tor_uvezen" value="0">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <x-platforma-student-search :current="$student->platforma_student_id ? ['platforma_student_id' => $student->platforma_student_id, 'platforma_upis_id' => $student->platforma_upis_id, 'ime' => $student->ime, 'prezime' => $student->prezime, 'br_indexa' => $student->br_indexa, 'nivo_studija_naziv' => $student->nivoStudija->naziv ?? null] : null" />
            <p x-show="platformaLinked" x-cloak class="md:col-span-2 -mt-2 mb-2 text-xs text-gray-500">Osnovni podaci se povlače sa studentske platforme i ovdje se ne mijenjaju. Za osvježavanje koristi dugme "Osvježi" na listi studenata.</p>

            @if($student->platforma_student_id)
              <p class="text-xs text-gray-500 -mt-4 mb-4">Poslednje osvježavanje sa platforme: {{ $student->platforma_synced_at?->format('d.m.Y H:i') ?? '-' }}.
                Osvježavanje se radi dugmetom na listi studenata.</p>
            @endif
          </div>

          <div class="mb-4">
            <label for="ime" class="block text-gray-700 font-medium mb-1">Ime</label>
            <input type="text" id="ime" name="ime" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" value="{{ old('ime', $student->ime) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('ime') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="prezime" class="block text-gray-700 font-medium mb-1">Prezime</label>
            <input type="text" id="prezime" name="prezime" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" value="{{ old('prezime', $student->prezime) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('prezime') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="br_indexa" class="block text-gray-700 font-medium mb-1">Broj indeksa</label>
            <input type="text" id="br_indexa" name="br_indexa" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" value="{{ old('br_indexa', $student->br_indexa) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('br_indexa') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="datum_rodjenja" class="block text-gray-700 font-medium mb-1">Datum rodjenja</label>
            <input type="date" id="datum_rodjenja" name="datum_rodjenja"
              value="{{ old('datum_rodjenja', $student->datum_rodjenja) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('datum_rodjenja') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="telefon" class="block text-gray-700 font-medium mb-1">Broj telefona</label>
            <input type="text" id="telefon" name="telefon" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" value="{{ old('telefon', $student->telefon) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('telefon') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="email" class="block text-gray-700 font-medium mb-1">Email</label>
            <input type="email" id="email" name="email" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" value="{{ old('email', $student->email) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="godina_studija" class="block text-gray-700 font-medium mb-1">Godina studija</label>
            <input type="number" id="godina_studija" name="godina_studija" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" x-model="studyYear" @input="checkYear()" min="0" 
              value="{{ old('godina_studija', $student->godina_studija) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('godina_studija') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4">
            <label for="jmbg" class="block text-gray-700 font-medium mb-1">JMBG</label>
            <input type="text" id="jmbg" name="jmbg" :readonly="platformaLinked" :class="platformaLinked ? \'bg-gray-100 text-gray-600\' : \'\'" value="{{ old('jmbg', $student->jmbg) }}"
              class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @error('jmbg') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4 md:col-span-2">
            <label class="block text-gray-700 font-medium mb-1">Pol</label>
            <div class="flex items-center space-x-6">
              <label class="inline-flex items-center">
                <input type="radio" name="pol" id="pol_muski" value="musko" {{ old('pol', $student->pol) == 'musko' ? 'checked' : '' }}
                  class="form-radio text-blue-600" />
                <span class="ml-2 text-gray-700">Muški</span>
              </label>

              <label class="inline-flex items-center">
                <input type="radio" name="pol" id="pol_zenski" value="zensko" {{ old('pol', $student->pol) == 'zensko' ? 'checked' : '' }}
                  class="form-radio text-blue-600" />
                <span class="ml-2 text-gray-700">Ženski</span>
              </label>
            </div>
          </div>

          <div class="mb-4 md:col-span-2">
            <label class="block text-gray-700 font-medium mb-1">Status</label>
            <select name="status" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="" disabled>Izaberi status</option>
                <option value="mobilnost" {{ old('status', $student->status) == 'mobilnost' ? 'selected' : '' }}>Mobilnost</option>
                <option value="prepis" {{ old('status', $student->status) == 'prepis' ? 'selected' : '' }}>Prepis</option>
            </select>
            @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
          </div>

          <div class="mb-4 md:col-span-2">
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Fakultet</label>
              <select name="fakultet_id" x-model="selectedFaculty" 
                @change="window.dispatchEvent(new CustomEvent('clear-selection')); fetchSubjects(); $dispatch('faculty-changed', $el.selectedOptions[0].text.trim())" 
                x-init="if(selectedFaculty) { fetchSubjects(); $nextTick(() => $dispatch('faculty-changed', $el.selectedOptions[0].text.trim())); }"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">Odaberi fakultet</option>
                @foreach($fakulteti as $f)
                  <option value="{{ $f->id }}">
                    {{ $f->naziv }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="mb-4 md:col-span-2">
            <div class="mb-4">
              <label class="block text-gray-700 font-medium mb-2">Nivo studija</label>
              <select name="nivo_studija_id" required x-model="studyLevel"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="window.dispatchEvent(new CustomEvent('study-level-changed', { detail: $el.value }))"
                x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('study-level-changed', { detail: $el.value })))">
                <option value="">Izaberi nivo studija</option>
                @foreach($nivoStudija as $nivo)
                  <option value="{{ $nivo->id }}" 
                    x-bind:disabled="parseInt(studyYear) > 3 && '{{ $nivo->naziv }}' !== 'Master'"
                  >
                    {{ $nivo->naziv }}
                  </option>
                @endforeach
              </select>
              @error('nivo_studija_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            @if($student->platforma_student_id && config('platforma.enabled'))
            <div class="mb-6 md:col-span-2">
              <div class="flex items-center justify-between mb-2">
                <label class="block text-gray-700 font-medium">Položeni ispiti sa studentske platforme</label>
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-green-100 text-green-800">SAMO PRIKAZ</span>
              </div>
              @if($platformaGreska)
                <p class="text-sm text-red-600">Platforma nije dostupna: {{ $platformaGreska }}</p>
              @elseif(empty($platformaPolozeni))
                <p class="text-sm text-gray-500">Student nema položenih ispita na platformi.</p>
              @else
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                      <tr>
                        <th class="px-3 py-2 text-left">Predmet</th>
                        <th class="px-3 py-2 text-left">Sem.</th>
                        <th class="px-3 py-2 text-left">ECTS</th>
                        <th class="px-3 py-2 text-left">Ocjena</th>
                        <th class="px-3 py-2 text-left">Bodovi</th>
                        <th class="px-3 py-2 text-left">Rok</th>
                        <th class="px-3 py-2 text-left">Ak. godina</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      @foreach($platformaPolozeni as $pp)
                      <tr>
                        <td class="px-3 py-2 text-gray-900">{{ $pp['naziv'] }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $pp['semestar'] }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $pp['ects'] }}</td>
                        <td class="px-3 py-2 font-semibold">{{ $pp['ocjena'] }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $pp['bodovi'] }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $pp['vrsta_roka'] }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $pp['akademska_godina'] }}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                <p class="mt-1 text-xs text-gray-500">Ovi ispiti se čitaju uživo sa platforme i ne čuvaju se u ovom sistemu. Pri kreiranju mobilnosti isključuju se iz liste nepoloženih.</p>
              @endif
            </div>
            @endif

            <div class="mb-4 md:col-span-2">
              <div class="mb-2">
                  <label class="block text-gray-700 font-medium">Predmeti</label>
              </div>
              
              {{-- Store full object for edit to prefill grades. fallback to old input if validation fails --}}
              @php
                  $selectedSubjects = $student->predmeti;
                  if($errors->any() && old('predmeti')) {
                      $selectedSubjects = [];
                      foreach(old('predmeti') as $id => $val) {
                          $grade = (is_array($val) && isset($val['grade'])) ? $val['grade'] : null;
                          $selectedSubjects[] = [
                              'id' => $id,
                              'pivot' => ['grade' => $grade]
                          ];
                      }
                  }
              @endphp
               <div x-show="hideLocalSubjects" x-cloak class="mb-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
              Položeni predmeti ovog studenta se čitaju sa studentske platforme, lokalni unos predmeta nije potreban.
            </div>
            <div x-show="!hideLocalSubjects">
            <x-subject-selector :subjects="$predmeti" :selected="$selectedSubjects">
                   <div x-data="{ visible: false }" @faculty-changed.window="visible = !!document.querySelector('select[name=fakultet_id]').value" x-show="visible" style="display: none;">
                      <button type="button" @click="$dispatch('open-tor-modal')"
                          class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-3 py-1 rounded shadow transform transition hover:scale-105">
                          Unesi TOR
                      </button>
                  </div>
              </x-subject-selector>
            </div>
              @if($errors->has('predmeti.*.grade'))
                <div class="mt-2 text-red-500 text-sm">
                    @foreach($errors->get('predmeti.*.grade') as $messages)
                        @foreach($messages as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    @endforeach
                </div>
              @endif
              @error('predmeti') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
          </div>
        </div>

        <div class="flex justify-end mt-6">
          <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg shadow-lg transform transition hover:scale-105">
            Ažuriraj studenta
          </button>
        </div>
      </form>

      <!-- ToR Upload Modal -->
      <div x-data="{ open: false }" @open-tor-modal.window="open = true" x-show="open" 
          x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
          x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
          x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4"
          style="display: none;">

          <div @click.away="open = false" class="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white"
               x-data="{ 
                   language: 'Engleski', 
                   uploading: false,
                   uploadTor() {
                       let fileInput = $refs.torFile;
                       if (!fileInput.files.length) {
                           alert('Please select a file');
                           return;
                       }

                       this.uploading = true;
                       let formData = new FormData();
                       formData.append('tor_file', fileInput.files[0]);
                       formData.append('language', this.language);
                       formData.append('fakultet_id', document.querySelector('select[name=\'fakultet_id\']').value);
                       formData.append('_token', '{{ csrf_token() }}');

                       fetch('{{ route('students.parse-tor') }}', {
                           method: 'POST',
                           body: formData,
                           headers: {
                               'Accept': 'application/json'
                           }
                       })
                       .then(response => response.json())
                       .then(data => {
                           this.uploading = false;
                           if (data.success) {
                               window.dispatchEvent(new CustomEvent('set-selection', { detail: data.matched }));
                               document.getElementById('tor_uvezen').value = 1;
                               alert(data.message);
                               this.open = false;
                               // Optional: Clear file input
                               fileInput.value = '';
                           } else {
                               alert(data.message || 'Upload failed');
                           }
                       })
                       .catch(error => {
                           this.uploading = false;
                           console.error('Error:', error);
                           alert('An error occurred during upload.');
                       });
                   }
               }">
              <div class="flex justify-between items-center mb-4">
                  <h3 class="text-xl font-bold text-gray-900">Pošalji Transcript of Records</h3>
                  <button @click="open = false" type="button" class="text-gray-400 hover:text-gray-500">
                      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                  </button>
              </div>

              <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Predmetni jezik</label>
                  <div class="flex space-x-4">
                      <label class="inline-flex items-center">
                          <input type="radio" name="language" value="Engleski" x-model="language" class="form-radio text-green-600">
                          <span class="ml-2">Engleski</span>
                      </label>
                      <label class="inline-flex items-center">
                          <input type="radio" name="language" value="Crnogorski" x-model="language" class="form-radio text-green-600">
                          <span class="ml-2">Crnogorski</span>
                      </label>
                  </div>
              </div>

              <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Posalji Word Document (.doc, .docx)</label>
                  <input type="file" x-ref="torFile" accept=".doc,.docx" class="block w-full text-sm text-gray-500
                      file:mr-4 file:py-2 file:px-4
                      file:rounded-full file:border-0
                      file:text-sm file:font-semibold
                      file:bg-green-50 file:text-green-700
                      hover:file:bg-green-100">
              </div>

              <div class="flex justify-end space-x-2">
                  <button @click="open = false" type="button"
                      class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm font-medium">
                      Otkaži
                  </button>
                  <button @click="uploadTor()" type="button" :disabled="uploading"
                      class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 shadow-lg transform hover:scale-105 transition-all font-medium flex items-center">
                      <span x-show="uploading" class="mr-2">
                          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                      </span>
                      <span x-text="uploading ? 'Processing...' : 'Upload & Process'"></span>
                  </button>
              </div>
          </div>
      </div>
    </div>
  </div>
</x-app-layout>