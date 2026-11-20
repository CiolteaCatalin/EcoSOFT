<?php

require __DIR__ . '/../includes/auth.php';
require_login();

require __DIR__ . '/../includes/db.php';

$q = trim($_GET['q'] ?? '');

if ($q !== '') {

    $stmt = $pdo->prepare(
        'SELECT
            o.*,
            c.name AS client_name,
            c.company
         FROM offers o
         JOIN clients c ON c.id = o.client_id
         WHERE o.offer_number LIKE ?
            OR c.name LIKE ?
            OR c.company LIKE ?
            OR o.status LIKE ?
         ORDER BY o.id DESC'
    );

    $like = '%' . $q . '%';

    $stmt->execute([
        $like,
        $like,
        $like,
        $like
    ]);

    $offers = $stmt->fetchAll();

} else {

    $offers = $pdo
        ->query(
            'SELECT
                o.*,
                c.name AS client_name,
                c.company
             FROM offers o
             JOIN clients c ON c.id = o.client_id
             ORDER BY o.id DESC'
        )
        ->fetchAll();
}

$totalOffers = count($offers);

require __DIR__ . '/../includes/header.php';

?>

<style>

.offers-page{
    display:grid;
    gap:18px;
}

.offers-toolbar{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:18px;
}

.offers-toolbar__copy h1{
    margin:3px 0 5px;
    font-size:27px;
    line-height:1.1;
    letter-spacing:-.03em;
    color:#172033;
}

.offers-toolbar__copy p{
    margin:0;
    color:#7b8593;
    font-size:12px;
}

.offers-count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:28px;
    height:23px;
    padding:0 8px;
    margin-left:6px;
    border-radius:999px;
    background:#edf3f8;
    color:#607083;
    font-size:10px;
    font-weight:700;
    vertical-align:middle;
}

.offers-search{
    display:flex;
    align-items:center;
    gap:8px;
    padding:9px;
    background:#fff;
    border:1px solid #e3e9ef;
    border-radius:12px;
}

.offers-search input{
    flex:1;
    min-width:280px;
    margin:0;
    border:0;
    background:transparent;
    box-shadow:none;
    padding:8px 10px;
}

.offers-search input:focus{
    border:0;
    box-shadow:none;
}

.offers-panel{
    background:#fff;
    border:1px solid #e3e9ef;
    border-radius:14px;
    overflow:hidden;
}

.offers-table{
    width:100%;
    border-collapse:collapse;
}

.offers-table th{
    background:#fafbfd;
    padding:11px 14px;
    border-bottom:1px solid #edf1f5;
    color:#7b8593;
    font-size:9px;
    font-weight:800;
    letter-spacing:.07em;
    text-transform:uppercase;
}

.offers-table td{
    padding:12px 14px;
    border-bottom:1px solid #f0f3f6;
    font-size:12px;
    vertical-align:middle;
}

.offers-table tbody tr:last-child td{
    border-bottom:0;
}

.offers-table tbody tr:hover{
    background:#fbfcfe;
}

.offer-number{
    font-weight:800;
    color:#172033;
    white-space:nowrap;
}

.offer-client{
    font-weight:600;
    color:#172033;
}

.offer-date{
    color:#6f7a89;
    white-space:nowrap;
}

.offer-total{
    font-weight:800;
    color:#172033;
    white-space:nowrap;
}

.offer-open{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:5px 10px;
    border:1px solid #1769aa;
    border-radius:7px;
    background:#1769aa;
    color:#fff;
    font-size:10px;
    font-weight:700;
    transition:.15s ease;
}

.offer-open:hover{
    background:#0f5b96;
    border-color:#0f5b96;
    color:#fff;
}

.badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:21px;
    padding:0 8px;
    border-radius:999px;
    font-size:8px;
    font-weight:800;
    text-transform:uppercase;
    white-space:nowrap;
}

.badge.draft{
    background:#eef1f5;
    color:#647082;
}

.badge.sent{
    background:#e5f2fd;
    color:#1769aa;
}

.badge.accepted{
    background:#e8f7ee;
    color:#27734a;
}

.badge.rejected{
    background:#fdeaea;
    color:#a13c3c;
}

.offers-empty{
    padding:36px 20px;
    text-align:center;
    color:#7b8593;
    font-size:12px;
}

@media(max-width:900px){

    .offers-toolbar{
        flex-direction:column;
        align-items:flex-start;
    }

    .offers-toolbar .btn{
        width:100%;
    }

}

@media(max-width:760px){

    .offers-search{
        flex-direction:column;
        align-items:stretch;
    }

    .offers-search input{
        min-width:0;
        width:100%;
    }

    .offers-panel{
        overflow:auto;
    }

    .offers-table{
        min-width:820px;
    }

}

.offer-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:6px;
    white-space:nowrap;
}

.offer-edit,
.offer-pdf{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:5px 10px;
    border-radius:7px;
    font-size:10px;
    font-weight:700;
    transition:.15s ease;
}

.offer-edit{
    background:#fff;
    border:1px solid #cfdbe6;
    color:#1769aa;
}

.offer-edit:hover{
    background:#f4f8fb;
    border-color:#abc1d3;
    color:#0f5b96;
}

.offer-pdf{
    background:#1769aa;
    border:1px solid #1769aa;
    color:#fff;
}

.offer-pdf:hover{
    background:#0f5b96;
    border-color:#0f5b96;
    color:#fff;
}

</style>


<div class="offers-page">

    <div class="offers-toolbar">

        <div class="offers-toolbar__copy">

            <span class="eyebrow">
                Sales
            </span>

            <h1>
                Oferte
                <span class="offers-count">
                    <?= (int)$totalOffers ?>
                </span>
            </h1>

            <p>
                Creează, urmărește și exportă ofertele comerciale.
            </p>

        </div>


        <a
            class="btn btn-primary"
            href="/offers/edit.php"
        >
            + Ofertă nouă
        </a>

    </div>


    <form
        class="offers-search"
        method="get"
    >

        <input
            type="search"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Caută după număr, client, companie sau status..."
        >

        <button
            type="submit"
            class="btn"
        >
            Caută
        </button>

        <?php if ($q !== ''): ?>

            <a
                href="/offers/"
                class="btn"
            >
                Reset
            </a>

        <?php endif; ?>

    </form>


    <div class="offers-panel">

        <?php if ($offers): ?>

            <div class="table-wrap">

                <table class="offers-table">

                    <thead>

                        <tr>
                            <th>Număr</th>
                            <th>Client</th>
                            <th>Data</th>
                            <th>Valabilă până</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($offers as $o): ?>

                        <tr>

                            <td>
                                <span class="offer-number">
                                    <?= e($o['offer_number']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="offer-client">
                                    <?= e(
                                        $o['company']
                                        ?: $o['client_name']
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span class="offer-date">
                                    <?= e($o['offer_date']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="offer-date">
                                    <?= e(
                                        $o['valid_until']
                                        ?? '-'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span class="offer-total">
                                    <?= money(
                                        (float)$o['grand_total']
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span
                                    class="badge <?= e($o['status']) ?>"
                                >
                                    <?= e($o['status']) ?>
                                </span>
                            </td>

    <td>
    <div class="offer-actions">

        <a
            class="offer-edit"
            href="/offers/edit.php?id=<?= (int)$o['id'] ?>"
        >
            Editează
        </a>

        <a
            class="offer-pdf"
            href="/offers/pdf.php?id=<?= (int)$o['id'] ?>"
            target="_blank"
            rel="noopener"
        >
            PDF
        </a>

    </div>
</td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="offers-empty">
                Nu există oferte pentru căutarea selectată.
            </div>

        <?php endif; ?>

    </div>

</div>


<?php

require __DIR__ . '/../includes/footer.php';