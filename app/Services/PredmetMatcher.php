<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Poređenje naziva predmeta koje toleriše razlike u velikim/malim slovima,
 * dijakriticima, interpunkciji, razmacima, skraćenicama i sitnim greškama u kucanju.
 */
class PredmetMatcher
{
    public const PRAG = 0.8;

    private const RIMSKI_BROJEVI = [
        'i' => '1', 'ii' => '2', 'iii' => '3', 'iv' => '4', 'v' => '5',
        'vi' => '6', 'vii' => '7', 'viii' => '8', 'ix' => '9', 'x' => '10',
    ];

    private const STOP_RIJECI = [
        'i', 'u', 'za', 'na', 'iz', 'od', 'sa', 'a',
        'and', 'of', 'the', 'in', 'to', 'for', 'with', 'on',
    ];

    /**
     * Vraća predmet čiji je naziv najsličniji zadatom, ili null ako nijedan ne prelazi prag
     * ili ako dva različita predmeta imaju isti najbolji rezultat.
     *
     * @param  array<string>  $polja  Atributi predmeta sa kojima se poredi (npr. ['naziv', 'naziv_engleski'])
     * @return array{predmet: mixed, slicnost: float}|null
     */
    public function najbolji(string $naziv, Collection $predmeti, array $polja, float $prag = self::PRAG): ?array
    {
        $najbolji = null;
        $najboljaSlicnost = 0.0;
        $nerijeseno = false;

        foreach ($predmeti as $predmet) {
            $slicnost = 0.0;
            foreach ($polja as $polje) {
                if (!empty($predmet->{$polje})) {
                    $slicnost = max($slicnost, $this->slicnost($naziv, $predmet->{$polje}));
                }
            }

            if ($slicnost > $najboljaSlicnost) {
                $najbolji = $predmet;
                $najboljaSlicnost = $slicnost;
                $nerijeseno = false;
            } elseif ($slicnost === $najboljaSlicnost && $najbolji && $predmet->id !== $najbolji->id) {
                $nerijeseno = true;
            }
        }

        if (!$najbolji || $najboljaSlicnost < $prag || ($nerijeseno && $najboljaSlicnost < 1.0)) {
            return null;
        }

        return ['predmet' => $najbolji, 'slicnost' => $najboljaSlicnost];
    }

    /**
     * Sličnost dva naziva u rasponu 0-1.
     */
    public function slicnost(string $a, string $b): float
    {
        $a = $this->normalizuj($a);
        $b = $this->normalizuj($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $rijeciA = explode(' ', $a);
        $rijeciB = explode(' ', $b);

        // "Matematika 1" i "Matematika 2" su različiti predmeti bez obzira na ostatak naziva
        if ($this->brojevi($rijeciA) !== $this->brojevi($rijeciB)) {
            return 0.0;
        }

        $poKarakterima = 1 - levenshtein($a, $b) / max(strlen($a), strlen($b));

        // 1.0 je rezervisano za nazive koji su isti nakon normalizacije
        return min(0.99, max(
            $poKarakterima,
            $this->slicnostRijeci($rijeciA, $rijeciB),
            $this->slicnostAkronima($rijeciA, $rijeciB),
        ));
    }

    public function normalizuj(string $naziv): string
    {
        $naziv = mb_strtolower(trim($naziv));
        $naziv = str_replace('đ', 'dj', $naziv);
        $naziv = Str::ascii($naziv);
        $naziv = preg_replace('/[^a-z0-9]+/', ' ', $naziv);

        $rijeci = array_values(array_filter(explode(' ', $naziv), fn ($r) => $r !== ''));

        // Rimski broj se tumači kao broj samo na kraju naziva, da se veznik "i" ne bi pretvorio u 1
        $posljednja = count($rijeci) - 1;
        if ($posljednja > 0 && isset(self::RIMSKI_BROJEVI[$rijeci[$posljednja]])) {
            $rijeci[$posljednja] = self::RIMSKI_BROJEVI[$rijeci[$posljednja]];
        }

        $rijeci = array_filter($rijeci, fn ($r) => !in_array($r, self::STOP_RIJECI, true));

        return implode(' ', $rijeci);
    }

    private function brojevi(array $rijeci): array
    {
        $brojevi = array_values(array_filter($rijeci, fn ($r) => ctype_digit($r)));
        sort($brojevi);

        return $brojevi;
    }

    /**
     * Udio riječi koje se poklapaju. Riječ se poklapa ako je ista, ako je skraćenica
     * druge riječi (npr. "osn" i "osnove") ili ako se razlikuje za jedno slovo.
     */
    private function slicnostRijeci(array $rijeciA, array $rijeciB): float
    {
        $preostale = $rijeciB;
        $poklopljene = 0;

        foreach ($rijeciA as $rijecA) {
            foreach ($preostale as $i => $rijecB) {
                if ($this->rijeciSePoklapaju($rijecA, $rijecB)) {
                    $poklopljene++;
                    unset($preostale[$i]);
                    break;
                }
            }
        }

        return 2 * $poklopljene / (count($rijeciA) + count($rijeciB));
    }

    private function rijeciSePoklapaju(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (ctype_digit($a) || ctype_digit($b)) {
            return false;
        }

        $kraca = strlen($a) <= strlen($b) ? $a : $b;
        $duza = $kraca === $a ? $b : $a;

        if (strlen($kraca) >= 3 && str_starts_with($duza, $kraca)) {
            return true;
        }

        return strlen($kraca) >= 5 && levenshtein($a, $b) <= 1;
    }

    /**
     * Prepoznaje akronime, npr. "OOP" i "Objektno orijentisano programiranje".
     */
    private function slicnostAkronima(array $rijeciA, array $rijeciB): float
    {
        foreach ([[$rijeciA, $rijeciB], [$rijeciB, $rijeciA]] as [$kratki, $dugi]) {
            if (count($kratki) !== 1 || count($dugi) < 2) {
                continue;
            }

            $inicijali = implode('', array_map(fn ($r) => ctype_digit($r) ? '' : $r[0], $dugi));
            if ($kratki[0] === $inicijali) {
                return 0.9;
            }
        }

        return 0.0;
    }
}
