<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 25mm 15mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #000; }
        h1 { text-align: center; font-size: 16px; margin: 15px 0 35px; }
        .informations { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .informations td { padding: 8px; }
        .cadre { border: 1px solid #000; text-align: center; }
        .resultats { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .resultats th, .resultats td { border: 1px solid #000; padding: 8px 4px; word-wrap: break-word; }
        .resultats th { background: #ddd; font-size: 8px; text-align: center; }
        .resultats th.resultat { background: #e2efd9; }
        .resultats td { height: 25px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>COMPTE RENDU DE FIN DE STAGE</h1>
    <table class="informations">
        <tr>
            <td>Libellé de la formation :</td>
            <td class="cadre"><strong>{{ $stage->libelle_court }}</strong></td>
            <td>Du : {{ $session->debut?->format('d/m/Y') }}<br>Au : {{ $session->fin?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Grade, prénom, nom du formateur :</td>
            <td class="cadre" colspan="2">
                @foreach ($formateurs as $formateur)
                    {{ trim(($formateur->grade?->libelle_court ?? '') . ' ' . $formateur->prenom . ' ' . $formateur->nom) }}@unless ($loop->last)<br>@endunless
                @endforeach
            </td>
        </tr>
    </table>
    <table class="resultats">
        <thead>
            <tr>
                <th style="width:8%;">GRADE</th>
                <th style="width:22%;">NOM</th>
                <th style="width:12%;">MATRICULE</th>
                <th style="width:13%;">UNITÉ</th>
                <th class="resultat" style="width:14%;">RÉSULTAT</th>
                <th class="resultat" style="width:14%;">DATE D’ATTRIBUTION</th>
                <th class="resultat" style="width:17%;">OBS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stagiaires as $inscription)
                <tr>
                    <td>{{ $inscription->grade }}</td>
                    <td>{{ $inscription->nom_complet }}</td>
                    <td>{{ $inscription->matricule }}</td>
                    <td>{{ $inscription->unite }}</td>
                    <td>{{ $resultats[$inscription->getKey()] }}</td>
                    <td>{{ $inscription->date_attribution?->format('d/m/Y') }}</td>
                    <td>{{ $inscription->observations_fin_stage }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
