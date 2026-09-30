<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <title>
        Candidature au stage
        {{ $inscription->sessionStage?->stage?->libelle_court }}
    </title>

    <style>
        @page {
            margin: 42px 48px;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.45;
        }

        .header {
            padding-bottom: 18px;
            margin-bottom: 24px;
            border-bottom: 2px solid #2563eb;
        }

        h1 {
            margin: 0 0 8px 0;
            color: #1e3a8a;
            font-size: 22px;
        }

        .reference {
            color: #64748b;
            font-size: 11px;
        }

        .stage-box {
            margin-bottom: 22px;
            padding: 14px 16px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
        }

        .stage-label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
        }

        .stage-name {
            margin-top: 4px;
            color: #1e3a8a;
            font-size: 18px;
            font-weight: bold;
        }

        .priority-warning {
            margin-bottom: 22px;
            padding: 12px 14px;
            background: #fffbeb;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            color: #92400e;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        td.label {
            width: 34%;
            color: #475569;
            font-weight: bold;
        }

        td.value {
            color: #111827;
        }

        .footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 10px;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>
        Candidature au stage
        {{ $inscription->sessionStage?->stage?->libelle_court ?? 'Non renseigné' }}
    </h1>

    <div class="reference">
        Référence de candidature :
        {{ $inscription->code_inscription }}
    </div>
</div>

<div class="stage-box">
    <div class="stage-label">
        Stage demandé
    </div>

    <div class="stage-name">
        {{ $inscription->sessionStage?->stage?->libelle_court ?? 'Non renseigné' }}
    </div>
</div>

@if ($inscription->stage_deja_effectue)
    <div class="priority-warning">
        <strong>Candidature non prioritaire :</strong>
        vous avez déjà effectué ce stage.
    </div>
@endif

<table>
    <tr>
        <td class="label">Motif</td>
        <td class="value">
            {{ $inscription->motif_inscription_label }}
        </td>
    </tr>

    <tr>
        <td class="label">Nom</td>
        <td class="value">
            {{ $inscription->nom ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">Prénom</td>
        <td class="value">
            {{ $inscription->prenom ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">Grade</td>
        <td class="value">
            {{ $inscription->grade ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">Brevet</td>
        <td class="value">
            {{ $inscription->brevet ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">Matricule</td>
        <td class="value">
            {{ $inscription->matricule ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">Spécialité</td>
        <td class="value">
            {{ $inscription->specialite ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">NID</td>
        <td class="value">
            {{ $inscription->nid ?: 'Non renseigné' }}
        </td>
    </tr>

    <tr>
        <td class="label">Bâtiment / unité</td>
        <td class="value">
            {{ $inscription->unite ?: 'Non renseigné' }}
        </td>
    </tr>
</table>

<div class="footer">
    Document généré lors de l'enregistrement de la candidature.
    Cette fiche atteste uniquement de la prise en compte de la candidature
    et ne vaut pas confirmation définitive de participation au stage.
</div>

</body>
</html>
