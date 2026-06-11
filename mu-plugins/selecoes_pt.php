<?php
/**
 * Mapeamento de seleções nacionais:
 *  - código FIFA (3 letras, vindo do football-data.org) → nome em PT-BR + ISO 3166-1 alpha-2 para bandeira.
 * Bandeiras servidas por flagcdn.com (w40 ≈ 40px de largura).
 */

if (!function_exists('bahia_traduz_grupo')) {
    function bahia_traduz_grupo($valor)
    {
        $valor = (string) $valor;
        if ($valor === '') return '';
        // football-data devolve "GROUP_A", "GROUP_B", etc.
        if (preg_match('/^GROUP[_\s-]?([A-Z0-9]+)$/i', $valor, $m)) {
            return 'Grupo ' . strtoupper($m[1]);
        }
        return $valor;
    }
}

if (!function_exists('bahia_traduz_fase')) {
    function bahia_traduz_fase($valor)
    {
        $map = [
            'GROUP_STAGE'       => 'Fase de grupos',
            'LAST_32'           => 'Repescagem (16-avos)',
            'LAST_16'           => 'Oitavas de final',
            'ROUND_OF_16'       => 'Oitavas de final',
            'QUARTER_FINALS'    => 'Quartas de final',
            'SEMI_FINALS'       => 'Semifinais',
            'THIRD_PLACE'       => 'Disputa de 3º lugar',
            'FINAL'             => 'Final',
            'PLAYOFFS'          => 'Repescagem',
        ];
        $valor = strtoupper((string) $valor);
        return $map[$valor] ?? str_replace('_', ' ', $valor);
    }
}

if (!function_exists('bahia_selecao_info')) {
    function bahia_selecao_info($tla, $fallback_name = '')
    {
        static $map = [
            // Américas
            'BRA' => ['nome' => 'Brasil',          'iso' => 'br'],
            'ARG' => ['nome' => 'Argentina',       'iso' => 'ar'],
            'URU' => ['nome' => 'Uruguai',         'iso' => 'uy'],
            'URY' => ['nome' => 'Uruguai',         'iso' => 'uy'],
            'PAR' => ['nome' => 'Paraguai',        'iso' => 'py'],
            'COL' => ['nome' => 'Colômbia',        'iso' => 'co'],
            'CHI' => ['nome' => 'Chile',           'iso' => 'cl'],
            'ECU' => ['nome' => 'Equador',         'iso' => 'ec'],
            'PER' => ['nome' => 'Peru',            'iso' => 'pe'],
            'BOL' => ['nome' => 'Bolívia',         'iso' => 'bo'],
            'VEN' => ['nome' => 'Venezuela',       'iso' => 've'],
            'MEX' => ['nome' => 'México',          'iso' => 'mx'],
            'USA' => ['nome' => 'Estados Unidos',  'iso' => 'us'],
            'CAN' => ['nome' => 'Canadá',          'iso' => 'ca'],
            'CRC' => ['nome' => 'Costa Rica',      'iso' => 'cr'],
            'PAN' => ['nome' => 'Panamá',          'iso' => 'pa'],
            'HON' => ['nome' => 'Honduras',        'iso' => 'hn'],
            'JAM' => ['nome' => 'Jamaica',         'iso' => 'jm'],
            'HAI' => ['nome' => 'Haiti',           'iso' => 'ht'],
            'CUB' => ['nome' => 'Cuba',            'iso' => 'cu'],
            'TRI' => ['nome' => 'Trinidad e Tobago', 'iso' => 'tt'],
            'CUW' => ['nome' => 'Curaçao',         'iso' => 'cw'],
            'SUR' => ['nome' => 'Suriname',        'iso' => 'sr'],

            // Europa
            'POR' => ['nome' => 'Portugal',        'iso' => 'pt'],
            'ESP' => ['nome' => 'Espanha',         'iso' => 'es'],
            'FRA' => ['nome' => 'França',          'iso' => 'fr'],
            'ITA' => ['nome' => 'Itália',          'iso' => 'it'],
            'GER' => ['nome' => 'Alemanha',        'iso' => 'de'],
            'ENG' => ['nome' => 'Inglaterra',      'iso' => 'gb-eng'],
            'SCO' => ['nome' => 'Escócia',         'iso' => 'gb-sct'],
            'WAL' => ['nome' => 'País de Gales',   'iso' => 'gb-wls'],
            'NIR' => ['nome' => 'Irlanda do Norte','iso' => 'gb-nir'],
            'IRL' => ['nome' => 'Irlanda',         'iso' => 'ie'],
            'NED' => ['nome' => 'Países Baixos',   'iso' => 'nl'],
            'BEL' => ['nome' => 'Bélgica',         'iso' => 'be'],
            'SUI' => ['nome' => 'Suíça',           'iso' => 'ch'],
            'AUT' => ['nome' => 'Áustria',         'iso' => 'at'],
            'POL' => ['nome' => 'Polônia',         'iso' => 'pl'],
            'CRO' => ['nome' => 'Croácia',         'iso' => 'hr'],
            'SRB' => ['nome' => 'Sérvia',          'iso' => 'rs'],
            'DEN' => ['nome' => 'Dinamarca',       'iso' => 'dk'],
            'SWE' => ['nome' => 'Suécia',          'iso' => 'se'],
            'NOR' => ['nome' => 'Noruega',         'iso' => 'no'],
            'FIN' => ['nome' => 'Finlândia',       'iso' => 'fi'],
            'ISL' => ['nome' => 'Islândia',        'iso' => 'is'],
            'CZE' => ['nome' => 'República Tcheca','iso' => 'cz'],
            'SVK' => ['nome' => 'Eslováquia',      'iso' => 'sk'],
            'HUN' => ['nome' => 'Hungria',         'iso' => 'hu'],
            'ROU' => ['nome' => 'Romênia',         'iso' => 'ro'],
            'BUL' => ['nome' => 'Bulgária',        'iso' => 'bg'],
            'UKR' => ['nome' => 'Ucrânia',         'iso' => 'ua'],
            'RUS' => ['nome' => 'Rússia',          'iso' => 'ru'],
            'TUR' => ['nome' => 'Turquia',         'iso' => 'tr'],
            'GRE' => ['nome' => 'Grécia',          'iso' => 'gr'],
            'ALB' => ['nome' => 'Albânia',         'iso' => 'al'],
            'BIH' => ['nome' => 'Bósnia e Herzegovina', 'iso' => 'ba'],
            'SVN' => ['nome' => 'Eslovênia',       'iso' => 'si'],
            'MKD' => ['nome' => 'Macedônia do Norte', 'iso' => 'mk'],
            'MNE' => ['nome' => 'Montenegro',      'iso' => 'me'],
            'GEO' => ['nome' => 'Geórgia',         'iso' => 'ge'],
            'ARM' => ['nome' => 'Armênia',         'iso' => 'am'],
            'AZE' => ['nome' => 'Azerbaijão',      'iso' => 'az'],

            // África
            'MAR' => ['nome' => 'Marrocos',        'iso' => 'ma'],
            'TUN' => ['nome' => 'Tunísia',         'iso' => 'tn'],
            'ALG' => ['nome' => 'Argélia',         'iso' => 'dz'],
            'EGY' => ['nome' => 'Egito',           'iso' => 'eg'],
            'SEN' => ['nome' => 'Senegal',         'iso' => 'sn'],
            'NGA' => ['nome' => 'Nigéria',         'iso' => 'ng'],
            'CMR' => ['nome' => 'Camarões',        'iso' => 'cm'],
            'GHA' => ['nome' => 'Gana',            'iso' => 'gh'],
            'CIV' => ['nome' => 'Costa do Marfim', 'iso' => 'ci'],
            'RSA' => ['nome' => 'África do Sul',   'iso' => 'za'],
            'ANG' => ['nome' => 'Angola',          'iso' => 'ao'],
            'MOZ' => ['nome' => 'Moçambique',      'iso' => 'mz'],
            'KEN' => ['nome' => 'Quênia',          'iso' => 'ke'],
            'MLI' => ['nome' => 'Mali',            'iso' => 'ml'],
            'BFA' => ['nome' => 'Burkina Faso',    'iso' => 'bf'],
            'COD' => ['nome' => 'Rep. Dem. do Congo', 'iso' => 'cd'],
            'CGO' => ['nome' => 'Congo',           'iso' => 'cg'],
            'CPV' => ['nome' => 'Cabo Verde',      'iso' => 'cv'],

            // Ásia / Oceania
            'JPN' => ['nome' => 'Japão',           'iso' => 'jp'],
            'KOR' => ['nome' => 'Coreia do Sul',   'iso' => 'kr'],
            'PRK' => ['nome' => 'Coreia do Norte', 'iso' => 'kp'],
            'CHN' => ['nome' => 'China',           'iso' => 'cn'],
            'AUS' => ['nome' => 'Austrália',       'iso' => 'au'],
            'NZL' => ['nome' => 'Nova Zelândia',   'iso' => 'nz'],
            'IRN' => ['nome' => 'Irã',             'iso' => 'ir'],
            'IRQ' => ['nome' => 'Iraque',          'iso' => 'iq'],
            'KSA' => ['nome' => 'Arábia Saudita',  'iso' => 'sa'],
            'QAT' => ['nome' => 'Catar',           'iso' => 'qa'],
            'UAE' => ['nome' => 'Emirados Árabes Unidos', 'iso' => 'ae'],
            'JOR' => ['nome' => 'Jordânia',        'iso' => 'jo'],
            'PLE' => ['nome' => 'Palestina',       'iso' => 'ps'],
            'SYR' => ['nome' => 'Síria',           'iso' => 'sy'],
            'LBN' => ['nome' => 'Líbano',          'iso' => 'lb'],
            'OMA' => ['nome' => 'Omã',             'iso' => 'om'],
            'BHR' => ['nome' => 'Bahrein',         'iso' => 'bh'],
            'KUW' => ['nome' => 'Kuwait',          'iso' => 'kw'],
            'UZB' => ['nome' => 'Uzbequistão',     'iso' => 'uz'],
            'THA' => ['nome' => 'Tailândia',       'iso' => 'th'],
            'VIE' => ['nome' => 'Vietnã',          'iso' => 'vn'],
            'IDN' => ['nome' => 'Indonésia',       'iso' => 'id'],
            'MAS' => ['nome' => 'Malásia',         'iso' => 'my'],
            'IND' => ['nome' => 'Índia',           'iso' => 'in'],
        ];

        $tla = strtoupper((string) $tla);
        if (isset($map[$tla])) {
            return [
                'nome' => $map[$tla]['nome'],
                'flag' => 'https://flagcdn.com/w40/' . $map[$tla]['iso'] . '.png',
            ];
        }
        return [
            'nome' => $fallback_name !== '' ? $fallback_name : $tla,
            'flag' => '',
        ];
    }
}
