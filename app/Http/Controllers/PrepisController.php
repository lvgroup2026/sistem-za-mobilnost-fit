<?php

namespace App\Http\Controllers;

use App\Models\Fakultet;
use App\Models\Predmet;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Services\WordExportService;

class PrepisController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\MappingRequest::with(['professor', 'student', 'fakultet', 'subjects.straniPredmet', 'subjects.fitPredmet', 'subjects.professor'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Search student
                $q->whereHas('student', function($qs) use ($search) {
                    $qs->where('ime', 'like', "%{$search}%")
                      ->orWhere('prezime', 'like', "%{$search}%")
                      ->orWhere('br_indexa', 'like', "%{$search}%");
                })
                // Search faculty
                ->orWhereHas('fakultet', function($qf) use ($search) {
                    $qf->where('naziv', 'like', "%{$search}%");
                });
            });
        }

        $statusi = ['u_obradi', 'spremno', 'prihvaceni', 'odbijeni'];
        $brojPoStatusu = ['svi' => (clone $query)->count()];
        foreach ($statusi as $status) {
            $brojPoStatusu[$status] = $this->filtrirajPoStatusu(clone $query, $status)->count();
        }

        $aktivniStatus = in_array($request->status, $statusi) ? $request->status : 'svi';
        if ($aktivniStatus !== 'svi') {
            $this->filtrirajPoStatusu($query, $aktivniStatus);
        }

        $mappingRequests = $query->paginate(7)->withQueryString();

        return view('prepis.index', compact('mappingRequests', 'brojPoStatusu', 'aktivniStatus'));
    }

    private function filtrirajPoStatusu($query, string $status)
    {
        // Ispit je neobrađen dok ga profesor nije ni povezao ni odbio
        $neobradjen = function ($q) {
            $q->whereNull('fit_predmet_id')
              ->where(fn ($q) => $q->where('is_rejected', false)->orWhereNull('is_rejected'));
        };

        return match ($status) {
            'u_obradi' => $query->where('status', 'pending')
                ->where(fn ($q) => $q->whereHas('subjects', $neobradjen)->orWhereDoesntHave('subjects')),
            'spremno' => $query->where('status', 'pending')
                ->whereHas('subjects')
                ->whereDoesntHave('subjects', $neobradjen),
            'prihvaceni' => $query->where('status', 'accepted'),
            'odbijeni' => $query->where('status', 'rejected'),
        };
    }



    public function edit($id)
    {
        $prepis = Prepis::with(['student', 'fakultet', 'agreements.fitPredmet', 'agreements.straniPredmet'])->findOrFail($id);
        $studenti = Student::all();
        $fakulteti = Fakultet::all();
        $predmeti = Predmet::select('id', 'naziv', 'ects', 'fakultet_id')->get();

        $existingAgreements = PrepisAgreement::select('fit_predmet_id', 'strani_predmet_id')
            ->distinct()
            ->get()
            ->groupBy('fit_predmet_id')
            ->map(function ($items) {
                return $items->pluck('strani_predmet_id');
            });

        $existingAgreementsForeign = PrepisAgreement::select('fit_predmet_id', 'strani_predmet_id')
            ->distinct()
            ->get()
            ->groupBy('strani_predmet_id')
            ->map(function ($items) {
                return $items->pluck('fit_predmet_id');
            });

        $mappedSubjects = \App\Models\MappingRequestSubject::whereNotNull('fit_predmet_id')
            ->get()
            ->pluck('fit_predmet_id', 'strani_predmet_id');

        $mappedSubjectsReverse = \App\Models\MappingRequestSubject::whereNotNull('fit_predmet_id')
            ->get()
            ->groupBy('fit_predmet_id')
            ->map(function ($items) {
                return $items->pluck('strani_predmet_id');
            });

        return view('prepis.edit', compact('prepis', 'studenti', 'fakulteti', 'predmeti', 'existingAgreements', 'existingAgreementsForeign', 'mappedSubjects', 'mappedSubjectsReverse'));
    }

    public function update(Request $request, $id)
    {
        $prepis = Prepis::findOrFail($id);

        $request->validate([
            'student_id' => 'required|exists:studenti,id',
            'fakultet_id' => 'required|exists:fakulteti,id',
            'datum' => 'required|date',
            'agreements' => 'nullable|array',
            'agreements.*.fit_predmet_id' => 'required|exists:predmeti,id',
            'agreements.*.strani_predmet_id' => 'required|exists:predmeti,id',
        ]);

        $prepis->update([
            'student_id' => $request->student_id,
            'fakultet_id' => $request->fakultet_id,
            'datum' => $request->datum,
        ]);

        $prepis->agreements()->delete();

        if ($request->has('agreements')) {
            foreach ($request->agreements as $agreementData) {
                PrepisAgreement::create([
                    'prepis_id' => $prepis->id,
                    'fit_predmet_id' => $agreementData['fit_predmet_id'],
                    'strani_predmet_id' => $agreementData['strani_predmet_id'],
                    'status' => 'u procesu',
                ]);
            }
        }

        return redirect()->route('prepis.index')->with('success', 'Prepis je uspješno ažuriran.');
    }

    public function destroy($id)
    {
        $prepis = Prepis::findOrFail($id);
        $prepis->agreements()->delete();
        $prepis->delete();
        return redirect()->route('prepis.index')->with('success', 'Prepis je uspješno obrisan.');
    }

    public function show($id)
    {
        $prepis = Prepis::with(['student', 'fakultet', 'agreements.fitPredmet', 'agreements.straniPredmet'])->findOrFail($id);
        return view('prepis.show', compact('prepis'));
    }


    public function match()
    {
        $professors = \App\Models\User::where('type', 1)->get();
        $students = Student::where('status', 'prepis')
            ->whereHas('predmeti')
            ->whereDoesntHave('fakulteti', function ($query) {
                $query->where('naziv', 'FIT');
            })
            ->get();
        
        $previousMatches = \App\Models\MappingRequestSubject::whereNotNull('fit_predmet_id')
            ->with(['professor', 'fitPredmet'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('strani_predmet_id')
            ->mapWithKeys(function ($item) {
                return [$item->strani_predmet_id => [
                    'professor_id' => $item->professor_id,
                    'professor_name' => $item->professor->name,
                    'fit_predmet_id' => $item->fit_predmet_id,
                    'fit_predmet_name' => $item->fitPredmet->naziv,
                    'date' => $item->created_at->format('d.m.Y'),
                ]];
            });

        // Fetch ALL pending matches grouped by subject ID
        // This allows us to see if a subject is pending with a professor for ANY student
        $globalPendingMatches = \App\Models\MappingRequestSubject::whereHas('mappingRequest', function($q) {
                $q->where('status', 'pending');
            })
            ->where(function($q) {
                $q->where('is_rejected', false)
                  ->orWhereNull('is_rejected');
            })
            ->with(['professor'])
            ->get()
            ->unique('strani_predmet_id')
            ->mapWithKeys(function($item) {
                return [$item->strani_predmet_id => [
                    'professor_id' => $item->professor_id,
                    'professor_name' => $item->professor->name,
                    'status' => 'pending'
                ]];
            });
        
        return view('prepis.match', compact('professors', 'students', 'previousMatches', 'globalPendingMatches'));
    }

    public function storeMatch(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:studenti,id',
            'matches' => 'required|array',
            'matches.*.professor_id' => 'required|exists:users,id',
            'matches.*.subject_id' => 'required|exists:predmeti,id',
            'matches.*.fit_predmet_id' => 'nullable|exists:predmeti,id',
        ]);
        
       
        $firstMatch = $request->matches[0];
        $firstSubject = \App\Models\Predmet::find($firstMatch['subject_id']);
        $fakultetId = $firstSubject ? $firstSubject->fakultet_id : null;

        $mappingRequest = \App\Models\MappingRequest::create([
            'professor_id' => null, // No single professor anymore
            'student_id' => $request->student_id,
            'fakultet_id' => $fakultetId,
            'status' => 'pending',
        ]);

        foreach ($request->matches as $match) {
            \App\Models\MappingRequestSubject::create([
                'mapping_request_id' => $mappingRequest->id,
                'strani_predmet_id' => $match['subject_id'],
                'professor_id' => $match['professor_id'], // Assign professor to the subject
                'fit_predmet_id' => $match['fit_predmet_id'] ?? null,
            ]);
        }


        return response()->json(['message' => 'Zahtjev je uspješno poslat.']);
    }

    public function getStudentSubjects($studentId)
    {
        $student = Student::with('predmeti')->findOrFail($studentId);
        return response()->json($student->predmeti);
    }

    public function showMappingRequest($id)
    {
        $mappingRequest = \App\Models\MappingRequest::with(['professor', 'student', 'fakultet', 'subjects.straniPredmet', 'subjects.fitPredmet'])->findOrFail($id);
        $fitSubjects = Predmet::where('fakultet_id', 1)->get(); 
        
        // Fetch existing foreign subjects in this request to exclude them
        // Exclude rejected ones so they can be re-added/updated
        $existingSubjectIds = $mappingRequest->subjects()
            ->where(function($q) {
                $q->where('is_rejected', false)
                  ->orWhereNull('is_rejected');
            })
            ->pluck('strani_predmet_id')
            ->toArray();
        
        // Fetch ALL subjects for this student that are NOT already in this request
        // Ensure to fetch subjects that belong to the mapping request's faculty? 
        // Or just all student's subjects? Usually mobility is one Uni.
        // Let's filter by student's enrolled subjects.
        // Assuming student->predmeti (many-to-many) exists.
        
        $studentSubjects = $mappingRequest->student->predmeti()
            ->whereNotIn('predmeti.id', $existingSubjectIds)
            // Optional: Filter by faculty if needed, but student might have subjects from multiple?
            // For now, let's filter by the faculty of the request to be safe/consistent?
            // If the request is for "University of X", we only want subjects from there.
            ->where('fakultet_id', $mappingRequest->fakultet_id)
            ->get();

        // Fetch professors for the dropdown (needed for drag-and-drop)
        $professors = \App\Models\User::where('type', 1)->orderBy('name')->get();

        return view('prepis.mapping_request_show', compact('mappingRequest', 'fitSubjects', 'studentSubjects', 'professors'));
    }

    public function addMappingRequestSubject(Request $request, $id)
    {
        // Legacy single add - can keep or remove. Keeping for robustnes if needed via API, but UI will use Bulk.
        // Refactoring to just use Bulk or keep as fallback.
        // Let's keep it.
        $mappingRequest = \App\Models\MappingRequest::findOrFail($id);
        // ... (existing logic)
        $request->validate([
            'strani_predmet_id' => 'required|exists:predmeti,id',
            'professor_id' => 'required|exists:users,id',
        ]);
        
        if ($mappingRequest->subjects()->where('strani_predmet_id', $request->strani_predmet_id)->exists()) {
             return redirect()->back()->with('error', 'Predmet je već u ovom zahtjevu.');
        }

        \App\Models\MappingRequestSubject::create([
            'mapping_request_id' => $mappingRequest->id,
            'strani_predmet_id' => $request->strani_predmet_id,
            'professor_id' => $request->professor_id,
        ]);

        return redirect()->back()->with('success', 'Predmet je dodat.');
    }

    public function storeBulkSubjects(Request $request, $id)
    {
        $mappingRequest = \App\Models\MappingRequest::findOrFail($id);

        $request->validate([
            'matches' => 'required|array',
            'matches.*.professor_id' => 'required|exists:users,id',
            'matches.*.subject_id' => 'required|exists:predmeti,id',
        ]);

        $count = 0;
        $count = 0;
        foreach ($request->matches as $match) {
            $existingSubject = $mappingRequest->subjects()->where('strani_predmet_id', $match['subject_id'])->first();

            if ($existingSubject) {
                if ($existingSubject->is_rejected) {
                    // Reactivate rejected subject
                    $existingSubject->update([
                        'professor_id' => $match['professor_id'],
                        'is_rejected' => false,
                        'fit_predmet_id' => null, // Reset match
                    ]);
                    $count++;
                }
                // Else: already exists and active, skip
            } else {
                // Create new
                \App\Models\MappingRequestSubject::create([
                    'mapping_request_id' => $mappingRequest->id,
                    'strani_predmet_id' => $match['subject_id'],
                    'professor_id' => $match['professor_id'],
                ]);
                $count++;
            }

            // Propagate to other pending rejected requests
            \App\Models\MappingRequestSubject::where('strani_predmet_id', $match['subject_id'])
                ->where('id', '!=', $existingSubject ? $existingSubject->id : 0) // Exclude current
                ->where('is_rejected', true)
                ->whereHas('mappingRequest', function($q) {
                    $q->where('status', 'pending');
                })
                ->update([
                    'professor_id' => $match['professor_id'],
                    'is_rejected' => false,
                    'fit_predmet_id' => null,
                ]);
        }

        return response()->json(['message' => "$count predmeta uspješno dodato."]);
    }

    public function updateMappingRequestSubject(Request $request, $id)
    {
        $subject = \App\Models\MappingRequestSubject::findOrFail($id);
        
        $request->validate([
            'fit_predmet_id' => 'nullable|exists:predmeti,id',
        ]);

        $subject->update(['fit_predmet_id' => $request->fit_predmet_id]);

        return redirect()->back()->with('success', 'Povezivanje ažurirano.');
    }

    public function removeMappingRequestSubject($id)
    {
         $subject = \App\Models\MappingRequestSubject::findOrFail($id);
         $subject->delete();
         return redirect()->back()->with('success', 'Predmet uklonjen iz zahtjeva.');
    }

    public function acceptMappingRequest($id)
    {
        $mappingRequest = \App\Models\MappingRequest::with('subjects')->findOrFail($id);
        
        if (!in_array($mappingRequest->status, ['pending', 'completed'])) {
            return redirect()->back()->with('error', 'Zahtjev nije na čekanju.');
        }

        $fitFaculty = Fakultet::where('naziv', 'FIT')->first();
        if (!$fitFaculty) {
             return redirect()->back()->with('error', 'Fakultet "FIT" nije pronađen u bazi.');
        }
        $fitFacultyId = $fitFaculty->id;

        $student = $mappingRequest->student;
        $student->fakulteti()->sync([$fitFacultyId]); 

        $syncData = [];
        
        $student->load('predmeti');

        // Više stranih predmeta može biti priznato kao jedan FIT predmet, pa se ocjene grupišu
        $ocjenePoFitPredmetu = [];
        foreach ($mappingRequest->subjects as $mappingSubject) {
            if ($mappingSubject->fit_predmet_id && !$mappingSubject->is_rejected) {
                $foreignSubject = $student->predmeti->firstWhere('id', $mappingSubject->strani_predmet_id);

                $ocjenePoFitPredmetu[$mappingSubject->fit_predmet_id][] = $foreignSubject ? $foreignSubject->pivot->grade : null;
            }
        }

        foreach ($ocjenePoFitPredmetu as $fitPredmetId => $ocjene) {
            $syncData[$fitPredmetId] = ['grade' => WordExportService::ocjenaPriznatogPredmeta($ocjene)];
        }
            
        // Use syncWithoutDetaching to ADD new subjects while keeping the Foreign ones
        if (!empty($syncData)) {
            $student->predmeti()->syncWithoutDetaching($syncData);
        }

        // Update mapping request with finalization data
        $mappingRequest->update([
            'status' => 'accepted',
            'datum_finalizacije' => now(),
        ]);

        // Priznati ispiti idu u karton studenta na platformi.
        $writeback = app(\App\Services\Platforma\PlatformaWriteback::class);
        if ($writeback->enabled() && $student->platforma_student_id) {
            if ($writeback->posaljiPrepis($mappingRequest)) {
                return redirect()->back()->with('success', 'Zahtjev za prepis je prihvaćen. Priznati ispiti su poslati u karton studenta na platformi.');
            }

            return redirect()->back()->with('error', 'Zahtjev je prihvaćen, ali slanje u platformu nije uspjelo: ' . $mappingRequest->fresh()->platforma_greska);
        }

        return redirect()->back()->with('success', 'Zahtjev za prepis je prihvaćen i ocjene su prenesene.');
    }

    public function rejectMappingRequest($id)
    {
        $mappingRequest = \App\Models\MappingRequest::findOrFail($id);
        
        if (!in_array($mappingRequest->status, ['pending', 'completed'])) {
            return redirect()->back()->with('error', 'Zahtjev nije na čekanju.');
        }

        $mappingRequest->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Zahtjev za prepis je odbijen.');
    }

    public function destroyMappingRequest($id)
    {
        $mappingRequest = \App\Models\MappingRequest::findOrFail($id);
        $mappingRequest->delete(); // Cascade will handle agreements and subjects
        return redirect()->back()->with('success', 'Zahtjev za prepis je uspješno obrisan.');
    }

    public function exportWord($id, WordExportService $service)
    {
        $mappingRequest = \App\Models\MappingRequest::with(['student.predmeti', 'subjects.straniPredmet', 'subjects.fitPredmet', 'subjects.professor', 'fakultet'])->findOrFail($id);

        if ($mappingRequest->status !== 'accepted') {
            return redirect()->back()->with('error', 'Samo prihvaćeni zahtjevi se mogu eksportovati.');
        }

        $filePath = $service->generisiPredlog($mappingRequest);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
    public function exportSolution($id, WordExportService $service)
    {
        $mappingRequest = \App\Models\MappingRequest::with(['student.predmeti', 'subjects.straniPredmet', 'subjects.fitPredmet', 'subjects.professor', 'fakultet'])->findOrFail($id);

        if ($mappingRequest->status !== 'accepted') {
            return redirect()->back()->with('error', 'Samo prihvaćeni zahtjevi se mogu eksportovati.');
        }

        $filePath = $service->generisiRjesenje($mappingRequest);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
