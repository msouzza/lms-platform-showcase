<?php

namespace App\Services;

use InvalidArgumentException;

class TafEvaluator
{
    public const METAS = [
        'masculino' => [
            'barra'         => 3.0,     // repetições
            'abdominal'     => 31.0,    // repetições
            'impulsao'      => 1.86,    // metros
            'braco_solo'    => 16.0,    // repetições
            'corrida'       => 2400.0,  // metros (12 min)
            'corrida_12min' => 2400.0,
        ],
        'feminino' => [
            'barra'         => 10.0,    // segundos (isometria)
            'abdominal'     => 25.0,    // repetições
            'impulsao'      => 1.46,    // metros
            'braco_solo'    => 12.0,    // repetições
            'corrida'       => 2000.0,  // metros (12 min)
            'corrida_12min' => 2000.0,
        ],
    ];

    /**
     * Avalia aprovação do candidato no teste TAF com metas mínimas oficiais.
     *
     * @param string $sexo 'masculino' ou 'feminino'
     * @param string $tipoTeste 'barra', 'abdominal', 'impulsao', 'braco_solo', 'corrida', etc.
     * @param float $resultado
     * @return array
     */
    public function evaluate(string $sexo, string $tipoTeste, float $resultado): array
    {
        $sexoNorm = strtolower(trim($sexo));
        $tipoNorm = strtolower(trim($tipoTeste));

        if (!isset(self::METAS[$sexoNorm])) {
            throw new InvalidArgumentException("Sexo inválido para avaliação TAF: {$sexo}");
        }

        if (!isset(self::METAS[$sexoNorm][$tipoNorm])) {
            throw new InvalidArgumentException("Tipo de teste inválido para avaliação TAF: {$tipoTeste}");
        }

        $minimo = self::METAS[$sexoNorm][$tipoNorm];
        $aprovado = $resultado >= $minimo;

        return [
            'status'    => $aprovado ? 'APROVADO' : 'REPROVADO',
            'aprovado'  => $aprovado,
            'minimo'    => $minimo,
            'resultado' => $resultado,
            'diferenca' => round($resultado - $minimo, 2),
            'sexo'      => $sexoNorm,
            'tipo'      => $tipoNorm,
        ];
    }
}
