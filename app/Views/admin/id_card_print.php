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

        <?= view('partials/id_card_style') ?>

        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .idc-page-controls { display: none !important; }
            /* Both faces already sit side-by-side here; scale the sheet so a
               cut-out card comes out close to the real CR80 size. */
            .idc-pair { zoom: 1.3; }
        }
    </style>
</head>
<body>
    <div class="idc-page-controls">
        <button type="button" class="idc-btn" onclick="window.print()">Print This Sheet</button>
        <a class="idc-btn idc-btn--secondary" href="<?= base_url('admin/id-cards/view/' . (int) ($student['id'] ?? 0)) ?>">Back to Preview</a>
        <a class="idc-btn idc-btn--secondary" href="<?= base_url('admin/id-cards') ?>">Back to List</a>
    </div>

    <div class="idc-stage">
        <div class="idc-pair">
            <div class="idc-pair__item">
                <div class="idc-pair__label">Front</div>
                <?= view('partials/id_card_front', ['student' => $student]) ?>
            </div>
            <div class="idc-pair__item">
                <div class="idc-pair__label">Back</div>
                <?= view('partials/id_card_back', ['student' => $student]) ?>
            </div>
        </div>
        <p class="idc-hint no-print">Print this sheet, cut along the card edges, then laminate. The front and the back of the card are printed side-by-side.</p>
    </div>
</body>
</html>
