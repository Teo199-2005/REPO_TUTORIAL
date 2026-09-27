<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Times New Roman', Times, serif;
            background: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .idc-page-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-bottom: 1rem;
        }

        .idc-btn {
            background: #007bff;
            color: #fff;
            border: 0;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .idc-btn:hover { background: #0056b3; }
        .idc-btn--secondary { background: #6c757d; }
        .idc-btn--secondary:hover { background: #545b62; }

        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .idc-page-controls,
            .idc-hint { display: none !important; }
            .idc-stage { gap: 0; }
        }

        <?= view('partials/id_card_style') ?>
    </style>
</head>
<body>
    <div class="idc-page-controls">
        <button type="button" class="idc-btn" onclick="window.print()">Print Front &amp; Back</button>
        <a class="idc-btn idc-btn--secondary" href="<?= base_url('admin/id-cards') ?>">Back to List</a>
    </div>

    <div class="idc-stage">
        <div class="idc-flip" id="idc-flip" role="button" tabindex="0" aria-pressed="false" aria-label="Show the back of this ID card">
            <div class="idc-flip__inner">
                <?= view('partials/id_card_front', ['student' => $student]) ?>
                <?= view('partials/id_card_back', ['student' => $student]) ?>
            </div>
        </div>
        <p class="idc-hint" id="idc-hint">Showing the FRONT &mdash; click the card to flip it over.</p>
    </div>

    <?= view('partials/id_card_flip_script') ?>
</body>
</html>
