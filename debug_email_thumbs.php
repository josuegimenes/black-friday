<?php
define('SUBMIT_SKIP_EXECUTION', true);

require __DIR__ . '/submit.php';

$samplePayload = [
    'items' => [
        [
            'name' => 'VESTIDO BÁRBARA',
            'size' => 'G2',
            'color' => 'Branco Off',
            'quantity' => 1,
            'salePrice' => 114.90,
            'thumb' => PUBLIC_MEDIA_BASE . '/assets/img/vestidos-curtos/barbara-branco/vestido-branco-curto-rodado-reveillon-ano-novo-plus-vesteme-modas%20(1).jpg',
        ],
        [
            'name' => 'VESTIDO CAMILA',
            'size' => 'P',
            'color' => 'Preto',
            'quantity' => 2,
            'salePrice' => 159.00,
            'thumb' => PUBLIC_MEDIA_BASE . '/assets/img/vestidos-curtos/camila-preto/vestido-preto-social-midi-elegante-alcinha-soltinho-plus-size-vesteme-modas%20(1).jpg',
        ],
    ],
    'totals' => [
        'totalItems' => 3,
        'totalValue' => 368.90,
        'totalSavings' => 520.00,
    ],
];

$_GET['debug'] = $_GET['debug'] ?? 'thumbs';

send_confirmation_email(
    'teste@vesteme.com.br',
    'Cliente Teste',
    $samplePayload,
    [
        'host' => '',
        'username' => '',
        'password' => '',
    ],
);
