<?php
//TODO: ALTERAR A KEY PARA PROD
return [
    "VINDI_API_KEY" => "uh9B3p5fDuo3eVoKSNup6doFmczbipNn",
    "planos" => [
        [
            "id" => 14649,
            "titulo" => "Assista por 1 mês<br>R$ 14,00",
//    		"titulo_promocional_inativos" => "Assista por 1 mês<br>R$ 9,80",
            "valor" => "R$ 14,00",
//            "valor_promocional_inativos" => 9.80,
//            "desconto_promocional_inativos" => 4.20,
            "desconto_promocional_percent"=>'',
            "desconto_percent"=>'',
            "produto" => 31633,
            "periodicidade" => 1,
            "cartao" => true,
            "boleto" => false,
        ],
        [
            "id" => 14652,
            "titulo" => "Pague 10 meses e assista 12 meses por <br>R$ 140,00",
//            "titulo_promocional_inativos" => "Pague 10 meses e assista 12 meses por <br>R$ 117,60",
            "valor" => "R$ 140,00",
//            "valor_promocional_inativos" => 117.60,
            "desconto"=>28.00,
            "desconto_percent"=>'-16.6%',
            "produto" => 31640,
            "periodicidade" => 12,
            "cartao" => true,
            "boleto" => false,
        ],
        [
            "id" => 14651,
            "titulo" => "6 meses pré-pagos<br>R$ 84,00",
            "valor" => "R$ 84,00",
            "produto" => 31637,
            "periodicidade" => 6,
            "cartao" => false,
            "boleto" => true,
        ],
        [
            "id" => 14652,
            "titulo" => "12 meses pré-pagos<br>R$ 168,00",
            "valor" => "R$ 168,00",
            "produto" => 31635,
            "periodicidade" => 12,
            "cartao" => false,
            "boleto" => true,
        ],
    ]
];