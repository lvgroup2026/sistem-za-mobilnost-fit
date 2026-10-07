<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\NivoStudija;
use App\Models\Fakultet;
use App\Models\Predmet;
use Illuminate\Http\Request;
use App\Services\TorImportService;
use App\Services\PredmetMatcher;
use App\Services\AutoPrepisService;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
  public function index(Request $request)
  {
    $query = Student::with(['nivoStudija', 'fakulteti']);

    if ($request->has('status') && in_array($request->status, ['mobilnost', 'prepis'])) {
        if ($request->status === 'mobilnost') {
            $query->where('status', 'mobilnost')
                  ->whereHas('fakulteti', function($q) {
                      $q->where('naziv', 'FIT');
                  });
        } elseif ($request->status === 'prepis') {
            $query->where('status', 'prepis')
                  ->whereHas('fakulteti', function($q) {
                      $q->where('naziv', '!=', 'FIT');
                  });
        }
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('ime', 'like', "%{$search}%")
              ->orWhere('prezime', 'like', "%{$search}%")
              ->orWhere('br_indexa', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    $students = $query->orderBy('created_at', 'desc')->paginate(7)->withQueryString();
    $nivoStudija = NivoStudija::all();
    $fakulteti = Fakultet::all();

    return view('students.index', compact('students', 'nivoStudija', 'fakulteti'));
  }

  public function create()
  {
    $nivoStudija = NivoStudija::all();
    $predmeti = collect(); // Start empty, will be loaded via API when faculty is selected
    $fakulteti = Fakultet::all();
    return view('students.create', compact('nivoStudija', 'predmeti', 'fakulteti'));
  }

  public function store(Request $request, AutoPrepisService $autoPrepis)
  {
    $validated = $request->validate([
      'ime' => 'required|string|max:255',
      'prezime' => 'required|string|max:255',
      'br_indexa' => 'required|string|max:20|unique:studenti',
      'datum_rodjenja' => 'nullable|date',
      'telefon' => 'nullable|string|max:255',
      'email' => 'nullable|email|max:255|unique:studenti,email',
      'platforma_student_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::unique('studenti')->where(fn ($q) => $q->where('platforma_upis_id', $request->input('platforma_upis_id')))],
      'platforma_upis_id' => 'nullable|integer',
      'godina_studija' => 'required|integer',
      'jmbg' => 'required|string|min:13|max:20|unique:studenti,jmbg',
      'nivo_studija_id' => [
          'required',
          'exists:nivo_studija,id',
          function ($attribute, $value, $fail) use ($request) {
              if ($request->godina_studija > 4) {
                  $master = NivoStudija::where('naziv', 'Master')->first();
                  if (!$master || $value != $master->id) {
                      $fail('Za godinu studija veću od 4, nivo studija mora biti Master.');
                  }
              }
          },
      ],
      'pol' => 'required|string|in:musko,zensko',
      'fakultet_id' => 'required|exists:fakulteti,id',
      'predmeti' => 'array',
      'predmeti.*' => 'array', // Each item in predmeti should be an array (e.g., ['grade' => 7])
      'predmeti.*.grade' => 'required|integer|min:6|max:10', // Validate grades if present
      'status' => 'required|in:mobilnost,prepis',
    ]);

    if (!empty($validated['platforma_student_id'])) {
      $validated['platforma_synced_at'] = now();
    }
    $student = Student::create($validated);

    if ($request->has('predmeti')) {
      $syncData = [];
      foreach ($request->predmeti as $id => $data) {
        // Ensure the ID itself is a valid subject ID before syncing
        if (Predmet::where('id', $id)->exists()) {
          $syncData[$id] = ['grade' => $data['grade'] ?? null];
        }
      }
      $student->predmeti()->sync($syncData);
    }

    if ($request->has('fakultet_id')) {
      $student->fakulteti()->sync([$request->fakultet_id]);
    }

    $poruka = 'Student created successfully!';
    if ($request->boolean('tor_uvezen')) {
      $poruka .= ' ' . $this->automatskiPrepis($student, $autoPrepis);
    }

    return redirect()->route('students.index')
      ->with('success', trim($poruka));
  }

  public function show($id)
  {
    $student = Student::with([
        'nivoStudija',
        'fakulteti',
        'predmeti.fakultet',
        'mappingRequests.fakultet',
        'mappingRequests.subjects.straniPredmet',
        'mappingRequests.subjects.fitPredmet',
    ])->findOrFail($id);

    // FIT predmeti dobijeni prepisom: fit_predmet_id => nazivi stranih predmeta na osnovu kojih su priznati
    $priznatiNaOsnovu = [];
    foreach ($student->mappingRequests->where('status', 'accepted') as $zahtjev) {
        foreach ($zahtjev->subjects as $subject) {
            if ($subject->fit_predmet_id && !$subject->is_rejected) {
                $priznatiNaOsnovu[$subject->fit_predmet_id][] = $subject->straniPredmet->naziv;
            }
        }
    }

    $poFakultetima = $student->predmeti
        ->sortBy(fn ($p) => [$p->semestar, $p->naziv])
        ->groupBy(fn ($p) => $p->fakultet->naziv ?? 'Nepoznat fakultet')
        ->map(function ($predmeti) {
            $polozeni = $predmeti->filter(fn ($p) => $p->pivot->grade >= 6);

            return [
                'predmeti' => $predmeti,
                'polozeno' => $polozeni->count(),
                'ects' => $polozeni->sum('ects'),
                'prosjek' => $polozeni->count() ? round($polozeni->avg('pivot.grade'), 2) : null,
            ];
        });

    $sviPolozeni = $student->predmeti->filter(fn ($p) => $p->pivot->grade >= 6);
    $ukupno = [
        'polozeno' => $sviPolozeni->count(),
        'ects' => $sviPolozeni->sum('ects'),
        'prosjek' => $sviPolozeni->count() ? round($sviPolozeni->avg('pivot.grade'), 2) : null,
    ];

    return view('students.show', compact('student', 'poFakultetima', 'ukupno', 'priznatiNaOsnovu'));
  }

  public function edit($id)
  {
    $student = Student::with(['predmeti', 'fakulteti'])->findOrFail($id);
    $nivoStudija = NivoStudija::all();
    
    $studentFaculty = $student->fakulteti->first();
    if ($studentFaculty) {
        $predmeti = Predmet::where('fakultet_id', $studentFaculty->id)->get();
    } else {
        $predmeti = collect();
    }
    
    $fakulteti = Fakultet::all();

    // Položeni ispiti sa studentske platforme (samo prikaz, ne čuvaju se lokalno)
    $platformaPolozeni = null;
    $platformaGreska = null;
    if ($student->platforma_student_id && config('platforma.enabled')) {
        try {
            $platformaPolozeni = app(\App\Services\Platforma\PlatformaClient::class)
                ->polozeniPredmeti((int) $student->platforma_student_id);
        } catch (\App\Services\Platforma\PlatformaException $e) {
            $platformaGreska = $e->getMessage();
        }
    }

    return view('students.edit', compact('student', 'nivoStudija', 'predmeti', 'fakulteti', 'platformaPolozeni', 'platformaGreska'));
  }

  public function update(Request $request, $id, AutoPrepisService $autoPrepis)
  {
    $student = Student::findOrFail($id);

    $validated = $request->validate([
      'ime' => 'required|string|max:255',
      'prezime' => 'required|string|max:255',
      'br_indexa' => 'required|string|max:20|unique:studenti,br_indexa,' . $id,
      'datum_rodjenja' => 'nullable|date',
      'telefon' => 'nullable|string|max:255',
      'email' => 'nullable|email|max:255|unique:studenti,email,' . $id,
      'platforma_student_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::unique('studenti')->where(fn ($q) => $q->where('platforma_upis_id', $request->input('platforma_upis_id')))->ignore($id)],
      'platforma_upis_id' => 'nullable|integer',
      'godina_studija' => 'required|integer',
      'jmbg' => 'required|string|min:13|max:20|unique:studenti,jmbg,' . $id,
      'nivo_studija_id' => [
        'required',
        'exists:nivo_studija,id',
        function ($attribute, $value, $fail) use ($request) {
            if ($request->godina_studija > 4) {
                $master = NivoStudija::where('naziv', 'Master')->first();
                if (!$master || $value != $master->id) {
                    $fail('Za godinu studija veću od 4, nivo studija mora biti Master.');
                }
            }
        },
      ],
      'pol' => 'required|string|in:musko,zensko',
      'fakultet_id' => 'required|exists:fakulteti,id',
      'predmeti' => 'array',
      'predmeti.*' => 'array', // Each item in predmeti should be an array (e.g., ['grade' => 7])
      'predmeti.*.grade' => 'required|integer|min:6|max:10', // Validate grades if present
      'status' => 'required|in:mobilnost,prepis',
    ]);

    if (!empty($validated['platforma_student_id']) && $validated['platforma_student_id'] != $student->platforma_student_id) {
      $validated['platforma_synced_at'] = now();
    }
    $student->update($validated);

    if ($request->has('predmeti')) {
      $syncData = [];
      foreach ($request->predmeti as $id => $data) {
        if (!is_array($data)) {
          // If $data is not an array, assume it's just the subject ID
          // and set grade to null. This handles cases where only subject IDs are sent.
          $syncData[$data] = ['grade' => null];
        } else {
          // If $data is an array, assume it contains 'grade'
          $syncData[$id] = ['grade' => $data['grade'] ?? null];
        }
      }
      $student->predmeti()->sync($syncData);
    } else {
      $student->predmeti()->detach();
    }

    if ($request->has('fakultet_id')) {
      $student->fakulteti()->sync([$request->fakultet_id]);
    }

    $poruka = 'Student uspješno izmijenjen!';
    if ($request->boolean('tor_uvezen')) {
      $poruka .= ' ' . $this->automatskiPrepis($student, $autoPrepis);
    }

    return redirect()->route('students.index')
      ->with('success', trim($poruka));
  }

  public function destroy($id)
  {
    $student = Student::findOrFail($id);
    
    if ($student->mappingRequests) {
        foreach ($student->mappingRequests as $req) {
            $req->subjects()->delete();
            $req->delete();
        }
    }


    
    $student->predmeti()->detach();
    $student->fakulteti()->detach();

    $student->delete();

    return redirect()->route('students.index')
      ->with('success', 'Student deleted successfully!');
  }

  public function uploadTor(Request $request, int $id, TorImportService $importService, PredmetMatcher $matcher)
  {
      $request->validate([
          'tor_file' => 'required|file|mimes:doc,docx',
          'language' => 'required|in:Crnogorski,Engleski',
      ]);

      $student = Student::with('fakulteti')->findOrFail($id);

      if ($student->fakulteti->first()?->naziv !== 'FIT') {
          return back()->with('error', 'This feature is only available for FIT students.');
      }

      try {
          $file = $request->file('tor_file');
          $path = $file->path();

          $courses = $importService->loadCoursesWithGrades($path);
          $language = $request->input('language');
          $totalCount = count($courses);

          $facultyId = $student->fakulteti->first()->id;
          $availableSubjects = Predmet::where('fakultet_id', $facultyId)->get();

          [$matched, $missedSubjects] = $this->upariTorPredmete($courses, $availableSubjects, $language, $matcher);

          $syncData = [];
          foreach ($matched as $subjectId => $match) {
              $syncData[$subjectId] = ['grade' => $match['grade']];
          }

          if (!empty($syncData)) {
              $student->predmeti()->syncWithoutDetaching($syncData);
          }

          return redirect()->route('students.edit', $student->id)
              ->with('success', $this->torPoruka($matched, $missedSubjects, $totalCount));

      } catch (\Exception $e) {
          Log::error('ToR Upload Error: ' . $e->getMessage());
          return back()->with('error', 'Failed to process ToR file: ' . $e->getMessage());
      }
  }

  public function parseTor(Request $request, TorImportService $importService, PredmetMatcher $matcher)
  {
      $request->validate([
          'tor_file' => 'required|file|mimes:doc,docx',
          'language' => 'required|in:Crnogorski,Engleski',
          'fakultet_id' => 'required|exists:fakulteti,id',
      ]);

      try {
          $file = $request->file('tor_file');
          $path = $file->path();

          $courses = $importService->loadCoursesWithGrades($path);
          $language = $request->input('language');
          $facultyId = $request->input('fakultet_id');
          $totalCount = count($courses);

          $availableSubjects = Predmet::where('fakultet_id', $facultyId)->get();

          [$matched, $missedSubjects] = $this->upariTorPredmete($courses, $availableSubjects, $language, $matcher);

          $results = [];
          foreach ($matched as $match) {
              // Structure compatible with subject-selector
              $results[] = [
                  'id' => $match['predmet']->id,
                  'naziv' => $match['predmet']->naziv,
                  'semestar' => $match['predmet']->semestar,
                  'ects' => $match['predmet']->ects,
                  'pivot' => ['grade' => $match['grade']] // Simulate pivot structure
              ];
          }

          return response()->json([
              'success' => true,
              'matched' => $results,
              'message' => $this->torPoruka($matched, $missedSubjects, $totalCount),
              'missed' => $missedSubjects
          ]);

      } catch (\Exception $e) {
          Log::error('ToR Parse Error: ' . $e->getMessage());
          return response()->json([
              'success' => false,
              'message' => 'Failed to process ToR file: ' . $e->getMessage()
          ], 500);
      }
  }

  /**
   * Povezuje predmete iz ToR-a sa predmetima fakulteta po sličnosti naziva.
   * Najsigurnija poklapanja se dodjeljuju prva, tako da jedan predmet iz baze
   * ne može "uzeti" predmet koji se nekom drugom nazivu iz ToR-a poklapa bolje.
   *
   * @return array{0: array<int, array>, 1: array<string>}
   */
  private function upariTorPredmete(array $courses, $availableSubjects, string $language, PredmetMatcher $matcher): array
  {
      // Strani fakulteti često imaju engleski naziv u koloni "naziv", pa se porede oba naziva
      $polja = $language === 'Engleski' ? ['naziv_engleski', 'naziv'] : ['naziv', 'naziv_engleski'];

      $kandidati = [];
      foreach ($courses as $courseData) {
          $courseName = trim($courseData['Course']);
          $kandidati[] = [
              'naziv' => $courseName,
              'grade' => $this->mapGrade($courseData['Grade']),
              'match' => $matcher->najbolji($courseName, $availableSubjects, $polja),
          ];
      }

      usort($kandidati, fn ($a, $b) => ($b['match']['slicnost'] ?? 0) <=> ($a['match']['slicnost'] ?? 0));

      $matched = [];
      $missedSubjects = [];

      foreach ($kandidati as $kandidat) {
          $match = $kandidat['match'];

          if ($match && isset($matched[$match['predmet']->id])) {
              $preostali = $availableSubjects->whereNotIn('id', array_keys($matched));
              $match = $matcher->najbolji($kandidat['naziv'], $preostali, $polja);
          }

          if (!$match) {
              $missedSubjects[] = $kandidat['naziv'];
              Log::warning("Tor Import: No match found for '{$kandidat['naziv']}' (Lang: $language)");
              continue;
          }

          $matched[$match['predmet']->id] = [
              'predmet' => $match['predmet'],
              'grade' => $kandidat['grade'],
              'naziv_iz_tora' => $kandidat['naziv'],
              'slicnost' => $match['slicnost'],
          ];
      }

      return [$matched, $missedSubjects];
  }

  private function torPoruka(array $matched, array $missedSubjects, int $totalCount): string
  {
      $msg = "Tor je učitan. Učitano je " . count($matched) . " od $totalCount predmeta.";

      $priblizni = [];
      foreach ($matched as $match) {
          if ($match['slicnost'] < 1.0) {
              $priblizni[] = "\"{$match['naziv_iz_tora']}\" -> \"{$match['predmet']->naziv}\"";
          }
      }

      if (count($priblizni) > 0) {
          $msg .= " Prepoznato po sličnosti naziva (provjeriti): " . implode(', ', $priblizni) . ".";
      }

      if (count($missedSubjects) > 0) {
          $msg .= " Predmeti koji nisu učitani: " . implode(', ', $missedSubjects);
      }

      if (count($matched) === 0 && $totalCount > 0) {
          $msg .= ". Nijedan predmet nije prepoznat - provjerite da li je izabran fakultet sa kog je ToR.";
      }

      return $msg;
  }

  private function automatskiPrepis(Student $student, AutoPrepisService $autoPrepis): string
  {
      $rezultat = $autoPrepis->kreirajZahtjev($student->fresh());

      if ($rezultat['zahtjev']) {
          $radnja = $rezultat['zahtjev']->wasRecentlyCreated ? 'kreiran' : 'dopunjen';
          $msg = "Automatski je {$radnja} zahtjev za prepis ({$rezultat['dodato']} predmeta dodijeljeno profesorima).";
          if ($rezultat['preostalo'] > 0) {
              $msg .= " Broj predmeta koje treba ručno povezati: {$rezultat['preostalo']}.";
          }
          return $msg;
      }

      if ($rezultat['preostalo'] > 0) {
          return 'Zahtjev za prepis nije automatski kreiran jer nijedan predmet nije moguće automatski dodijeliti profesoru.';
      }

      return '';
  }

  private function mapGrade($gradeLetter)
  {
      $map = [
          'A' => 10,
          'B' => 9,
          'C' => 8,
          'D' => 7,
          'E' => 6,
          'F' => 5, 
      ];

      return $map[strtoupper(trim($gradeLetter))] ?? null;
  }
}
