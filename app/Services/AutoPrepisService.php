<?php

namespace App\Services;

use App\Models\Fakultet;
use App\Models\MappingRequest;
use App\Models\MappingRequestSubject;
use App\Models\Predmet;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Automatsko kreiranje zahtjeva za prepis nakon uvoza ToR-a.
 *
 * Za svaki strani predmet studenta traži se:
 *  1. ranije potvrđeno povezivanje istog predmeta (isti profesor i FIT predmet),
 *  2. ako ga nema, FIT predmet najsličnijeg naziva i profesor koji ga predaje.
 * Predmeti za koje se ne može odrediti profesor ostaju adminu za ručno povezivanje.
 */
class AutoPrepisService
{
    private const PRAG_PRIJEDLOGA = 0.85;

    public function __construct(private PredmetMatcher $matcher)
    {
    }

    /**
     * @return array{zahtjev: ?MappingRequest, dodato: int, preostalo: int}
     */
    public function kreirajZahtjev(Student $student): array
    {
        $rezultat = ['zahtjev' => null, 'dodato' => 0, 'preostalo' => 0];

        $student->loadMissing(['fakulteti', 'predmeti']);
        $fakultet = $student->fakulteti->first();
        $fit = Fakultet::where('naziv', 'FIT')->first();

        if ($student->status !== 'prepis' || !$fakultet || !$fit || $fakultet->id === $fit->id) {
            return $rezultat;
        }

        $straniPredmeti = $student->predmeti->where('fakultet_id', $fakultet->id);
        if ($straniPredmeti->isEmpty()) {
            return $rezultat;
        }

        $zahtjev = MappingRequest::where('student_id', $student->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $vecUZahtjevu = $zahtjev ? $zahtjev->subjects()->pluck('strani_predmet_id')->all() : [];
        $fitPredmeti = Predmet::where('fakultet_id', $fit->id)->with('profesori')->get();

        $stavke = [];
        foreach ($straniPredmeti as $predmet) {
            if (in_array($predmet->id, $vecUZahtjevu)) {
                continue;
            }

            $par = $this->prethodnoPovezivanje($predmet) ?? $this->prijedlogPovezivanja($predmet, $fitPredmeti);

            if (!$par) {
                $rezultat['preostalo']++;
                continue;
            }

            $stavke[] = ['strani_predmet_id' => $predmet->id] + $par;
        }

        if (empty($stavke)) {
            return $rezultat;
        }

        $zahtjev = DB::transaction(function () use ($zahtjev, $student, $fakultet, $stavke) {
            $zahtjev ??= MappingRequest::create([
                'professor_id' => null,
                'student_id' => $student->id,
                'fakultet_id' => $fakultet->id,
                'status' => 'pending',
            ]);

            foreach ($stavke as $stavka) {
                $zahtjev->subjects()->create($stavka);
            }

            return $zahtjev;
        });

        $rezultat['zahtjev'] = $zahtjev;
        $rezultat['dodato'] = count($stavke);

        return $rezultat;
    }

    private function prethodnoPovezivanje(Predmet $straniPredmet): ?array
    {
        $prethodni = MappingRequestSubject::where('strani_predmet_id', $straniPredmet->id)
            ->whereNotNull('fit_predmet_id')
            ->whereNotNull('professor_id')
            ->where('is_rejected', false)
            ->latest()
            ->first();

        if (!$prethodni) {
            return null;
        }

        return [
            'professor_id' => $prethodni->professor_id,
            'fit_predmet_id' => $prethodni->fit_predmet_id,
        ];
    }

    private function prijedlogPovezivanja(Predmet $straniPredmet, $fitPredmeti): ?array
    {
        $najslicniji = null;

        foreach (['naziv', 'naziv_engleski'] as $polje) {
            if (empty($straniPredmet->{$polje})) {
                continue;
            }

            $kandidat = $this->matcher->najbolji(
                $straniPredmet->{$polje},
                $fitPredmeti,
                ['naziv', 'naziv_engleski'],
                self::PRAG_PRIJEDLOGA
            );

            if ($kandidat && (!$najslicniji || $kandidat['slicnost'] > $najslicniji['slicnost'])) {
                $najslicniji = $kandidat;
            }
        }

        $profesor = $najslicniji ? $najslicniji['predmet']->profesori->first() : null;

        if (!$profesor) {
            return null;
        }

        return [
            'professor_id' => $profesor->id,
            'fit_predmet_id' => $najslicniji['predmet']->id,
        ];
    }
}
