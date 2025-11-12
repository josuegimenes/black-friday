<?php
function render_reservation_email(array $data): string
{
    $name = htmlspecialchars($data['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars($data['date_br'] ?? '', ENT_QUOTES, 'UTF-8');
    $logoUrl = htmlspecialchars($data['logo_url'] ?? '', ENT_QUOTES, 'UTF-8');
    $groupLink = htmlspecialchars($data['group_link'] ?? '#', ENT_QUOTES, 'UTF-8');
    $items = $data['items'] ?? [];
    $totals = $data['totals'] ?? ['items' => 0, 'value' => 'R$ 0,00', 'savings' => 'R$ 0,00'];

    ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Vesteme - Reserva confirmada</title>
</head>
<body style="margin:0;padding:0;background:#f7f7fb;font-family:'Inter',Arial,sans-serif;color:#111">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;margin:0 auto;padding:32px 16px;">
        <tr>
            <td style="text-align:center;padding-bottom:16px;">
                <img src="<?= $logoUrl ?>" alt="Vesteme Black" width="120" height="120" style="width:120px;height:120px;border-radius:999px;">
            </td>
        </tr>
        <tr>
            <td style="background:#ffffff;border-radius:28px;padding:32px;border:1px solid #efeff5;box-shadow:0 25px 50px rgba(13,15,35,0.08);">
                <p style="margin:0 0 12px;font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#ff007f;text-align:center;">Maior Black da Vésteme</p>
                <h1 style="margin:0 0 16px;font-size:26px;line-height:1.3;color:#07060c;text-align:center;"><?= $name ?>, recebemos o seu interesse nestes produtos!</h1>
                <p style="margin:0 0 24px;color:#4f4c5c;font-size:15px;line-height:1.6;text-align:center;">A reserva foi registrada em <strong><?= $date ?></strong>. Seguimos liberando os pagamentos até 28/11 (ou enquanto houver estoque), obedecendo a ordem de cadastro. Confira os itens abaixo:</p>

                <div style="border:1px solid #f0f1f6;border-radius:20px;overflow:hidden;margin-bottom:18px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f9f9fd;color:#6c6a7a;font-size:12px;text-transform:uppercase;letter-spacing:1px;">
                                <th align="left" style="padding:14px 18px;">Produto</th>
                                <th align="center" style="padding:14px 12px;">Qtd</th>
                                <th align="center" style="padding:14px 12px;">Grade</th>
                                <th align="right" style="padding:14px 18px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr style="font-size:14px;color:#110f1b;border-top:1px solid #f0f1f6;">
                                    <td style="padding:14px 18px;">
                                        <strong style="display:block;font-size:15px;"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span style="color:#8c8a98;font-size:12px;">Cor: <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td style="padding:14px 12px;text-align:center;"><?= (int) $item['quantity'] ?></td>
                                    <td style="padding:14px 12px;text-align:center;"><?= htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="padding:14px 18px;text-align:right;font-weight:600;"><?= htmlspecialchars($item['line_total'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:18px 22px;border-radius:20px;background:#0f0d20;color:#fff;margin-bottom:28px;display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
                    <div style="flex:1;min-width:220px;">
                        <span style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#bab5ff;">Investimento</span>
                        <p style="margin:8px 0 4px;font-size:30px;font-weight:700;"><?= htmlspecialchars($totals['value'], ENT_QUOTES, 'UTF-8') ?></p>
                        <small style="color:#bab5ff;"><?= htmlspecialchars($totals['items'], ENT_QUOTES, 'UTF-8') ?> itens selecionados</small>
                    </div>
                    <div style="flex:1;min-width:220px;text-align:right;">
                        <span style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#bab5ff;">Economia prevista</span>
                        <p style="margin:8px 0 4px;font-size:26px;font-weight:700;color:#46fcb4;"><?= htmlspecialchars($totals['savings'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <p style="margin:0 0 18px;color:#4f4c5c;font-size:15px;line-height:1.6;">Enquanto montamos os lotes finais, entre agora no grupo VIP do WhatsApp para receber os bastidores, confirmações e o link de pagamento assim que chegar sua vez.</p>
                <p style="text-align:center;margin:0 0 12px;">
                    <a href="<?= $groupLink ?>" style="display:inline-block;padding:16px 42px;border-radius:999px;background:#ff007f;color:#fff;text-decoration:none;font-weight:600;letter-spacing:1px;" target="_blank" rel="noopener">Entrar no grupo VIP</a>
                </p>
                <p style="margin:0;font-size:12px;color:#a09eb1;text-align:center;">Se precisar atualizar o carrinho, volte ao site e reenvie o formulário. Continuaremos garantindo a sua posição.</p>
            </td>
        </tr>
    </table>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
