<?php

namespace App\Services;

use App\Models\MappingRequest;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Tab;
use Carbon\Carbon;

class WordExportService
{
    public function generisiPredlog(MappingRequest $request)
    {
        $phpWord = new PhpWord();

        // Define styles
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        $this->dodajZaglavlje($section);

        $student = $request->student;
        $studentName = $student->ime . " " . $student->prezime;
        $zensko = $student->pol === 'zensko';
        $foreignFacultyName = $request->fakultet ? $request->fakultet->naziv : "Unknown Faculty";

        $justify = ['alignment' => Jc::BOTH, 'spaceAfter' => 160];
        $numbered = $justify + ['tabs' => [new Tab('left', 720)]];

        // 1. Broj & datum
        $section->addText("Broj:");
        $section->addText("Podgorica, " . $this->datumSlovima(Carbon::now()) . " godine");
        $section->addTextBreak(1);

        // 2. Preamble
        $textRun = $section->addTextRun($justify);
        $textRun->addText("Na osnovu čl. 19. Pravila studiranja na osnovnim studijama Univerziteta „Mediteran“ Podgorica, rješavajući po zahtjevu " . ($zensko ? "studentkinje " : "studenta ") . $studentName . ", dekanici Fakulteta za informacione tehnologije podnosim");

        // 3. Title
        $section->addText(
            "PREDLOG RJEŠENJA O PRIZNAVANJU ISPITA SA STRUČNIM MIŠLJENJIMA",
            ['bold' => true],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 240, 'spaceAfter' => 240]
        );

        // 4. Main Body Intro
        $yearString = match((int) $student->godina_studija) {
            1 => "prve",
            2 => "druge",
            3 => "treće",
            4 => "četvrte",
            default => $student->godina_studija . "."
        };

        $bodyRun = $section->addTextRun($justify);
        $bodyRun->addText($studentName, ['bold' => true]);
        $bodyRun->addText(", " . ($zensko ? "studentkinji" : "studentu") . " $yearString godine Fakulteta za informacione tehnologije Univerziteta „Mediteran“ Podgorica, priznaju se položeni ispiti i dobijene ocjene na $foreignFacultyName, kao položeni ispiti i dobijene ocjene na Fakultetu za informacione tehnologije Univerziteta „Mediteran“, kako slijedi:");

        // 5. Jedna tačka po FIT predmetu; više stranih predmeta može biti priznato kao jedan FIT predmet
        $grupe = $request->subjects
            ->filter(fn ($s) => !is_null($s->fit_predmet_id) && !$s->is_rejected)
            ->groupBy('fit_predmet_id');

        $counter = 1;
        foreach ($grupe as $grupa) {
            $fitPredmet = $grupa->first()->fitPredmet;
            $strani = $grupa->map(function ($subject) use ($student) {
                $studentSubject = $student->predmeti->find($subject->strani_predmet_id);
                return [
                    'naziv' => $subject->straniPredmet->naziv,
                    'ects' => $subject->straniPredmet->ects,
                    'ocjena' => $studentSubject?->pivot->grade,
                ];
            })->values();

            $fitOcjena = self::ocjenaPriznatogPredmeta($strani->pluck('ocjena')->all());

            $pRun = $section->addTextRun($numbered);
            $pRun->addText($counter . ".\t" . ($strani->count() > 1 ? "Ispiti " : "Ispit "));

            foreach ($strani as $i => $predmet) {
                if ($i > 0) {
                    $pRun->addText($i === $strani->count() - 1 ? " i " : ", ");
                }
                $pRun->addText($predmet['naziv'], ['bold' => true]);
            }

            if ($strani->count() === 1) {
                $predmet = $strani->first();
                $pRun->addText(", položen na $foreignFacultyName i sa dobijenom ocjenom " . $this->ocjenaSlovima($predmet['ocjena']) . ", {$predmet['ects']} ECTS, priznaje se");
            } else {
                $pRun->addText(", " . ($strani->count() === 2 ? "oba" : "svi") . " položeni na $foreignFacultyName i sa " . $this->ocjeneViseIspita($strani) . ", priznaje se");
            }

            $pRun->addText(" kao položen ispit na Fakultetu za informacione tehnologije Univerziteta „Mediteran“ Podgorica pod nazivom ");
            $pRun->addText($fitPredmet->naziv, ['bold' => true]);
            $pRun->addText(" sa ocjenom " . $this->ocjenaSlovima($fitOcjena) . ", {$fitPredmet->ects} ECTS.");

            $professor = $grupa->first(fn ($s) => $s->professor)?->professor;

            $misljenje = $section->addTextRun($justify);
            $misljenje->addText("Stručno mišljenje predmetnog nastavnika", ['bold' => true]);
            $misljenje->addText(": Uvidom u dostavljeni nastavni plan i program, ");
            $misljenje->addText("saglasan/na sam", ['bold' => true]);
            $misljenje->addText(" da se prizna ispit s obzirom na zaključak da se radi o predmetima podudarnog sadržaja i obima.");

            $section->addTextBreak(1);
            $section->addText("______________________________________", [], ['spaceAfter' => 0]);
            $section->addText($professor->name ?? '', [], ['spaceAfter' => 360]);

            $counter++;
        }

        // 6. Obrazloženje
        $section->addText("Obrazloženje", ['bold' => true], ['alignment' => Jc::CENTER, 'spaceBefore' => 240, 'spaceAfter' => 240]);

        $obrazlozenjeRun = $section->addTextRun($justify);
        $obrazlozenjeRun->addText("Uvidom u dostavljenu Molbu " . ($zensko ? "studentkinje " : "studenta ") . $studentName . ", Uvjerenje o položenim ispitima i Nastavne planove i programe, a nakon pribavljenih stručnih mišljenja predmetnih nastavnika, koji su sastavni dio ovog akta, predlažem da dekanica Fakulteta za informacione tehnologije Univerziteta „Mediteran“ Podgorica donese ");
        $obrazlozenjeRun->addText("rješenje o priznavanju položenih ispita", ['bold' => true]);
        $obrazlozenjeRun->addText(" navedenih u dispozitivu gore navedenog predloga.");

        $section->addTextBreak(2);

        // 7. Final Signature
        $section->addText("Prodekanka za nastavu", [], ['alignment' => Jc::RIGHT]);
        $section->addTextBreak(1);
        $section->addText("______________________________", [], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
        $section->addText("Doc. dr Žana Knežević", [], ['alignment' => Jc::RIGHT]);

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $fileBase = "predlog_priznavanja_{$student->ime}_{$student->prezime}_{$student->br_indexa}";
        $fileName = $fileBase . '_' . time() . '.docx';
        $tempPath = storage_path('app/public/' . $fileName);
        $objWriter->save($tempPath);

        return $tempPath;
    }

    /**
     * Ocjena FIT predmeta kada se priznaje na osnovu jednog ili više stranih predmeta
     * (zaokruženi prosjek ocjena stranih predmeta).
     */
    public static function ocjenaPriznatogPredmeta(array $ocjene): ?int
    {
        $ocjene = array_filter($ocjene, fn ($o) => is_numeric($o));

        return $ocjene ? (int) round(array_sum($ocjene) / count($ocjene)) : null;
    }

    private function dodajZaglavlje($section): void
    {
        $header = $section->addHeader();
        $table = $header->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();

        $lijevo = $table->addCell(1600, ['valign' => 'center']);
        $logoUniverziteta = public_path('logo_uni_mediteran.png');
        if (file_exists($logoUniverziteta)) {
            $lijevo->addImage($logoUniverziteta, ['width' => 85, 'height' => 85, 'alignment' => Jc::LEFT]);
        }

        $sredina = $table->addCell(6000, ['valign' => 'center']);
        $sredina->addText("UNIVERZITET „MEDITERAN” PODGORICA", ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $sredina->addText("FAKULTET ZA INFORMACIONE TEHNOLOGIJE", ['size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $desno = $table->addCell(1600, ['valign' => 'center']);
        $logoFakulteta = public_path('logo.png');
        if (file_exists($logoFakulteta)) {
            $desno->addImage($logoFakulteta, ['width' => 70, 'height' => 70, 'alignment' => Jc::RIGHT]);
        }

        $header->addTextBreak(1);
    }

    private function datumSlovima(Carbon $datum): string
    {
        $mjeseci = [
            1 => 'januar', 'februar', 'mart', 'april', 'maj', 'jun',
            'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar',
        ];

        return $datum->day . ". " . $mjeseci[$datum->month] . " " . $datum->year . ".";
    }

    private function ocjenaSlovima($ocjena): string
    {
        return match ((int) $ocjena) {
            10 => "„A – odličan“",
            9 => "„B – vrlo dobar“",
            8 => "„C – dobar“",
            7 => "„D – zadovoljava“",
            6 => "„E – dovoljan“",
            default => "„" . ($ocjena ?? '-') . "“",
        };
    }

    private function ocjeneViseIspita($strani): string
    {
        $isteOcjene = $strani->pluck('ocjena')->unique()->count() === 1;
        $istiEcts = $strani->pluck('ects')->unique()->count() === 1;

        if ($isteOcjene && $istiEcts) {
            return "dobijenom ocjenom " . $this->ocjenaSlovima($strani->first()['ocjena']) . " i {$strani->first()['ects']} ECTS svaki";
        }

        $dijelovi = $strani->map(fn ($p) => $this->ocjenaSlovima($p['ocjena']) . " ({$p['ects']} ECTS)")->all();
        $posljednji = array_pop($dijelovi);

        return "dobijenim ocjenama " . implode(', ', $dijelovi) . " i " . $posljednji;
    }

    public function generisiRjesenje(MappingRequest $request)
    {
        $phpWord = new PhpWord();
        
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $studentName = $request->student->ime . " " . $request->student->prezime;
        $foreignFacultyName = $request->fakultet ? $request->fakultet->naziv : "=";
        $year = $request->student->godina_studija;
        $yearString = match((int)$year) {
            1 => "prve",
            2 => "druge",
            3 => "treće",
            4 => "četvrte",
            default => $year . "."
        };

        $preamble = "Na osnovu člana 84 stav 4 alineja 7 Statuta Univerziteta “Mediteran” Podgorica i člana 19 Pravila studiranja na osnovnim studijama, rješavajući po zahtjevu studenta " . $studentName . ", na predlog prodekanke i nakon pribavljanih stručnih mišljenja predmetnih nastavnika/ca, dekanka Fakulteta donijela je:";
        $section->addText($preamble, [], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        $section->addText("RJEŠENJE", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
        $section->addText("o priznavanju položenih ispita", ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        $section->addText("I", ['bold' => true]);
        
        $countryName = $request->fakultet->drzava ?? ($request->fakultet->univerzitet->drzava ?? '');
        $countrySuffix = $countryName ? ", $countryName" : "";

        $introRun = $section->addTextRun(['alignment' => Jc::BOTH]);
        $introRun->addText($studentName, ['bold' => true]);
        $introRun->addText(", studentu $yearString godine Fakulteta za informacione tehnologije Univerziteta „Mediteran” Podgorica, priznaju se položeni ispiti sa fakulteta ");
        $introRun->addText($foreignFacultyName . $countrySuffix, ['bold' => true]);
        $introRun->addText(", kao položeni ispiti na fakultetu Fakulteta za informacione tehnologije Univerziteta „Mediteran” Podgorica, na sljedeći način:");
        $section->addTextBreak(1);

        $matchedSubjects = $request->subjects->filter(fn($s) => !is_null($s->fit_predmet_id));
        $counter = 1;

        foreach ($matchedSubjects as $subject) {
            $foreignSubjName = $subject->straniPredmet->naziv;
            $studentSubject = $request->student->predmeti->find($subject->strani_predmet_id);
            $grade = $studentSubject ? ($studentSubject->pivot->grade ?? '-') : '-';
            $foreignEcts = $subject->straniPredmet->ects;

            $fitSubjName = $subject->fitPredmet->naziv;
            $fitEcts = $subject->fitPredmet->ects;

            $translatedGrade = match((int)$grade) {
                10 => "10 (A – odličan)",
                9 => "9 (B – vrlo dobar)",
                8 => "8 (C – dobar)",
                7 => "7 (D – zadovoljan)",
                6 => "6 (E – dovoljan)",
                default => $grade
            };

            $textRun = $section->addTextRun(['alignment' => Jc::BOTH]);
            $textRun->addText($counter . ". Ispit ");
            $textRun->addText($foreignSubjName, ['italic' => true]);
            $textRun->addText(", položen na fakultetu $foreignFacultyName, vrednovan sa $foreignEcts ECTS kredita i ostvarenom ocjenom $translatedGrade, priznaje se kao položeni ispit na fakultetu Univerziteta “Mediteran” Podgorica pod nazivom ");
            $textRun->addText($fitSubjName, ['italic' => true, 'bold' => true]);
            $textRun->addText(", vrednovan sa $fitEcts ECTS i ocjenom $translatedGrade.", ['bold' => true]);
            
            $counter++;
        }
        $section->addTextBreak(1);

        $section->addText("II     Rješenje stupa na snagu danom donošenja.");
        $section->addText("III   Odluka je konačna na Univerzitetu.");
        $section->addTextBreak(1);

        $section->addText("Obrazloženje", ['bold' => true], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        $obrazlozenjeRun = $section->addTextRun(['alignment' => Jc::BOTH]);
        $obrazlozenjeRun->addText("Student $yearString godine osnovnih akademskih studija $foreignFacultyName, ");
        $obrazlozenjeRun->addText($studentName, ['bold' => true]);
        $obrazlozenjeRun->addText(", obratio se dekanki Fakulteta za informacione tehnologije, u cilju priznavanja navedenih ispita položenih na $foreignFacultyName. Imenovani student je, u prilog tome, dostavio i Uvjerenje o položenim ispitima na od $foreignFacultyName.");
        $section->addTextBreak(1);

        $obrazlozenjeRun2 = $section->addTextRun(['alignment' => Jc::BOTH]);
        $obrazlozenjeRun2->addText("Uvidom u službenu dokumentaciju imenovanog studenta, kao i uvidom u Nastavni plan i program Fakulteta, prodekanka Fakulteta za informacione tehnologije, donijela je predlog za priznavanje traženih ispita, uz prethodno pribavljanje stručnog mišljenja predmetnih nastavnika o priznavanju navedenih predmeta. Dekanka Fakulteta za informacione tehnologije Univerziteta „Mediteran“ Podgorica je, u smislu člana 19 Pravila studiranja na osnovnim studijama, i priavljenih stručnih mišljenja predmetnih nastavnika/ca, a na osnovu predloga prodekanke, odlučila kao u dispozitivu Rješenja.");
        $section->addTextBreak(1);

        $section->addText("Pravna pouka: Protiv ovog rješenja može se izjaviti žalba Vijeću Fakulteta u roku od 15 dana od prijema Rješenja.");
        $section->addTextBreak(2);

        $footerTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $footerTable->addRow();
        
        $leftCell = $footerTable->addCell(5000);
        $leftCell->addText("Dostavljeno :");
        $leftCell->addText("-studentu " . $studentName);
        $leftCell->addText("-u evidencioni karton studenta");
        $leftCell->addText("-arhivi Fakulteta");

        $rightCell = $footerTable->addCell(5000);
        $rightCell->addText("DEKANKA", ['bold' => true], ['alignment' => Jc::RIGHT]);
        $rightCell->addText("prof. dr Maja Delibašić", ['bold' => true], ['alignment' => Jc::RIGHT]);

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $student = $request->student;
        $fileBase = "rjesenje_o_priznavanju_{$student->ime}_{$student->prezime}_{$student->br_indexa}";
        $fileName = $fileBase . '_' . time() . '.docx';
        $tempPath = storage_path('app/public/' . $fileName);
        $objWriter->save($tempPath);

        return $tempPath;
    }

    public function generisiOdlukuZaMobilnost(\App\Models\Mobilnost $mobilnost)
    {
        $phpWord = new PhpWord();
        
        $phpWord->setDefaultFontName('Cambria');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        // 1. Header (Left aligned)
        $section->addText("Broj: _________");
        $section->addText("Podgorica, " . Carbon::now()->format('d.m.Y') . ". godine");
        $section->addTextBreak(1);

        // 2. Centered Intro Text
        $studentName = $mobilnost->student->ime . " " . $mobilnost->student->prezime;
        $introRun = $section->addTextRun(['alignment' => Jc::CENTER]);
        $introRun->addText("Dekanka Fakulteta za informacione tehnologije, na osnovu člana 84 stav 4 alineja 7 Statuta Univerziteta „Mediteran“ Podgorica, rješavajući po zahtjevu studenta ");
        $introRun->addText($studentName, ['bold' => true]);
        $introRun->addText(", za priznavanje ispita, donijela je");
        $section->addTextBreak(1);

        // 3. Centered Bold ODLUKU
        $section->addText("ODLUKU", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        // 4. Point 1 preamble
        $nivoStudija = $mobilnost->student->nivoStudija->naziv ?? '...';
        $brIndexa = $mobilnost->student->br_indexa;
        $foreignFacultyName = $mobilnost->fakultet ? $mobilnost->fakultet->naziv : "...";
        
        $p1Run = $section->addTextRun(['alignment' => Jc::BOTH]);
        $p1Run->addText("1. ");
        $p1Run->addText($studentName, ['bold' => true]);
        $p1Run->addText(" studentu ");
        $p1Run->addText($nivoStudija, ['bold' => true]);
        $p1Run->addText(" studija Fakulteta za informacione tehnologije, br. indeksa ");
        $p1Run->addText($brIndexa, ['bold' => true]);
        $p1Run->addText(", koji je u toku studijske ");
        $p1Run->addText($mobilnost->studijska_godina ?? '/', ['bold' => true]);
        $p1Run->addText(". godine studirao na ");
        $p1Run->addText($foreignFacultyName, ['bold' => true]);
        $p1Run->addText(", kao stipendista programa mobilnosti ERASMUS INTERNATIONAL CREDIT MOBILITY – ICM2020, priznaju se položeni sljedeći ispiti:");
        $section->addTextBreak(1);

        // 5. Table with foreign subjects
        $styleTable = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
        $phpWord->addTableStyle('MobilityTable', $styleTable);
        $table = $section->addTable('MobilityTable');
        
        $table->addRow();
        $table->addCell(1000)->addText("r. br.", ['bold' => true]);
        $table->addCell(5000)->addText("Naziv predmeta", ['bold' => true]);
        $table->addCell(2000)->addText("Broj ECTS kredita", ['bold' => true]);
        $table->addCell(2000)->addText("Ocjena", ['bold' => true]);

        $counter = 1;
        $totalEcts = 0;
        $gradeSum = 0;
        $gradeCount = 0;
        $gradeMap = ['A' => 10, 'B' => 9, 'C' => 8, 'D' => 7, 'E' => 6];

        // Filter for unique foreign subjects by name
        $uniqueAgreements = $mobilnost->learningAgreements
            ->whereNotNull('strani_predmet_id')
            ->unique(function ($la) {
                return trim($la->straniPredmet->naziv);
            });

        foreach ($uniqueAgreements as $la) {
            $foreignSubjName = $la->straniPredmet->naziv;
            $foreignEcts = $la->straniPredmet->ects;
            $grade = $la->ocjena;

            $table->addRow();
            $table->addCell(1000)->addText($counter . ".");
            $table->addCell(5000)->addText($foreignSubjName);
            $table->addCell(2000)->addText($foreignEcts);
            $table->addCell(2000)->addText($grade ?? '-');

            $totalEcts += $foreignEcts;
            
            if ($grade) {
                $upperGrade = strtoupper(trim($grade));
                if (isset($gradeMap[$upperGrade])) {
                    $gradeSum += $gradeMap[$upperGrade];
                    $gradeCount++;
                } elseif (is_numeric($grade)) {
                    $gradeSum += (float) $grade;
                    $gradeCount++;
                }
            }
            $counter++;
        }

        $numericAvg = $gradeCount > 0 ? ($gradeSum / $gradeCount) : null;
        $letterAvg = $this->mapNumericToLetter($numericAvg);

        $table->addRow();
        $table->addCell(1000);
        $table->addCell(5000)->addText("UKUPNO", ['bold' => true]);
        $table->addCell(2000)->addText($totalEcts, ['bold' => true]);
        $table->addCell(2000)->addText($letterAvg, ['bold' => true]);

        $section->addTextBreak(1);

        // 6. Point 2
        $p2Text = "2.	Imenovani student položio je ispite i odbranio jednogodišnji master rad u ukupnom obimu $totalEcts pod nazivom /.";
        $section->addText($p2Text);
        $section->addTextBreak(1);
        $section->addText("Master rad je ekvivalent specijalističkom radom.");
        $section->addTextBreak(1);
        
        $p2SubRun = $section->addTextRun();
        $p2SubRun->addText("Evidentiranje priznatih ispita, ocjena i ECTS kredita iz tačke 1, u personalnu dokumentaciju studenta ");
        $p2SubRun->addText($studentName, ['bold' => true]);
        $p2SubRun->addText(", kao i u Supplement diplome, obaviće se u skladu sa opštim aktima Univerziteta.");
        $section->addTextBreak(1);

        // 7. Obrazloženje
        $section->addText("OBRAZLOŽENJE", ['bold' => true], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        $obrRun1 = $section->addTextRun(['alignment' => Jc::BOTH]);
        $obrRun1->addText($studentName, ['bold' => true]);
        $obrRun1->addText(", student ");
        $obrRun1->addText($nivoStudija);
        $obrRun1->addText(" Fakulteta za informacione tehnologije, br. indeksa ");
        $obrRun1->addText($brIndexa);
        $obrRun1->addText(", boravio je u toku studijske ");
        $obrRun1->addText($mobilnost->studijska_godina ?? '/');
        $obrRun1->addText(" godine na ");
        $obrRun1->addText($foreignFacultyName);
        $obrRun1->addText(", kao stipendista programa mobilnosti ERASMUS+ - Key Action 1: International credit mobility for leraners and staff – ICM2020, saglasno Sporazumu o institucionalnoj saradnji sa ");
        $obrRun1->addText($foreignFacultyName);
        $obrRun1->addText(", br. R-2178-22 od 23.09.2022.godine.");
        $section->addTextBreak(1);

        $section->addText("Po završetku boravka na $foreignFacultyName, imenovani se obratio Fakultetu za informacione tehnologije sa Zahtjevom za priznavanje ispita iz sljedećih predmeta:");
        $section->addTextBreak(1);

        // Repeat table
        $table2 = $section->addTable('MobilityTable');
        $table2->addRow();
        $table2->addCell(1000)->addText("r. br.", ['bold' => true]);
        $table2->addCell(5000)->addText("Naziv predmeta", ['bold' => true]);
        $table2->addCell(2000)->addText("Broj ECTS kredita", ['bold' => true]);
        $table2->addCell(2000)->addText("Ocjena", ['bold' => true]);

        $counter = 1;
        foreach ($uniqueAgreements as $la) {
            $table2->addRow();
            $table2->addCell(1000)->addText($counter . ".");
            $table2->addCell(5000)->addText($la->straniPredmet->naziv);
            $table2->addCell(2000)->addText($la->straniPredmet->ects);
            $table2->addCell(2000)->addText($la->ocjena ?? '-');
            $counter++;
        }
        $table2->addRow();
        $table2->addCell(1000);
        $table2->addCell(5000)->addText("UKUPNO", ['bold' => true]);
        $table2->addCell(2000)->addText($totalEcts, ['bold' => true]);
        $table2->addCell(2000)->addText($letterAvg, ['bold' => true]);

        $section->addTextBreak(1);

        $section->addText("Na osnovu člana 1 stav 2 Pravila studiranja na postdiplomskims tudijama i analognom primjenom člana 20 Pravila studiranja na osnovnim studijama, propisano je da student ima pravo da u toku studija provede određeno vrijeme (semestar ili studijsku godinu) na drugoj ustanovi visokog obrazovanja u zemlji ili inostranstvu, posredstvom međunarodnih programa za razmjenu studenta (SOCRATES, ERASMUS, DAAD i slično) ili na bazi bilateralnih ugovora između univerziteta.", [], ['alignment' => Jc::BOTH]);
        $section->addTextBreak(1);
        $section->addText("Članom 2 Uputstva za primjenu pravila mobilnosti studenata u realizaciji Sporazuma o institucionalnoj saradnji sa $foreignFacultyName, propisano je da se studentu  koji po osnovu mobilnosti boravi na $foreignFacultyName, priznaju  i vrednuju svi predmeti, ocjene i krediti koje je stekao na $foreignFacultyName, kao da su stečeni i vrednovani na Fakultetu za informacione tehnologije.", [], ['alignment' => Jc::BOTH]);
        $section->addTextBreak(1);
        $section->addText("Na osnovu naprijed izloženog odlučeno je kao u dispozitivu Odluke.", [], ['alignment' => Jc::BOTH]);
        $section->addTextBreak(1);

        // 8. Footer (Dostaviti & Signature)
        $section->addPageBreak();
        $section->addText("Dostaviti:");
        $section->addText("-Studentskoj službi Fakulteta");
        $section->addText("-$studentName");
        $section->addText("-u dosije studenta");
        $section->addTextBreak(1);

        $section->addText("DEKANKA", ['bold' => true], ['alignment' => Jc::RIGHT]);
        $section->addText("__________________", [], ['alignment' => Jc::RIGHT]);
        $section->addText("prof dr Maja Delibasic", ['bold' => true], ['alignment' => Jc::RIGHT]);

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $student = $mobilnost->student;
        $fileBase = "odluka_mobilnost_{$student->ime}_{$student->prezime}_{$student->br_indexa}";
        $fileName = $fileBase . '_' . time() . '.docx';
        $tempPath = storage_path('app/public/' . $fileName);
        $objWriter->save($tempPath);

        return $tempPath;
    }

    private function mapNumericToLetter($avg)
    {
        if ($avg === null) return '-';
        if ($avg >= 9.5) return 'A';
        if ($avg >= 8.5) return 'B';
        if ($avg >= 7.5) return 'C';
        if ($avg >= 6.5) return 'D';
        if ($avg >= 6.0) return 'E';
        return 'F';
    }
}
