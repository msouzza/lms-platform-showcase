<?php

namespace App\Services;

class ScoreCalculator
{
    /**
     * Calcula nota percentual de forma segura.
     * - Clamp entre 0.0 e 100.0
     * - Proteção contra divisão por zero (retorna 0.0)
     * - Arredondamento a 2 casas decimais
     *
     * @param int|float $acertos
     * @param int|float $total
     * @return float
     */
    public function calculate(int|float $acertos, int|float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        if ($acertos <= 0) {
            return 0.0;
        }

        $score = ($acertos / $total) * 100;

        if ($score < 0) {
            $score = 0.0;
        } elseif ($score > 100) {
            $score = 100.0;
        }

        return round((float) $score, 2);
    }
}
