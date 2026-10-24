{{-- From the RGESN self-assessment in resources/audits/rgesn.json and the commitments of ADR-0006 (E1.5). --}}
<x-statement-page title="Déclaration d'écoconception">
    <p>balise s'appuie sur le référentiel général d'écoconception des services numériques (RGESN), version 2024. Cette déclaration est une auto-évaluation du mainteneur du site, établie le {{ \Illuminate\Support\Carbon::parse($statement['date'])->translatedFormat('j F Y') }}.</p>

    <h2>Score</h2>
    <p>{{ $statement['score'] }} % des critères applicables sont validés : {{ $statement['counts']['S'] }} sur {{ $statement['counts']['S'] + $statement['counts']['NS'] }}. {{ $statement['counts']['NA'] }} critères ne s'appliquent pas au service.</p>

    <h2>Engagements et mesures</h2>
    <ul>
        <li>Au plus 600 Ko de JavaScript compressé sur la page de la carte, vérifié à chaque modification du code ; 505 Ko le 24 octobre 2026.</li>
        <li>Seules les positions qui changent sont envoyées au navigateur, et une réponse sans changement est un 304 sans corps : les données suivent le rythme du flux (20 s), même si la page demande toutes les 5 s.</li>
        <li>La page cesse de demander les positions quand son onglet est masqué ou que le bouton Pause est activé.</li>
        <li>Polices du système, aucune image, aucune mesure d'audience.</li>
    </ul>

    @foreach (['NS' => 'Critères non validés et ce qui est prévu', 'S' => 'Critères validés', 'NA' => 'Critères non applicables'] as $status => $heading)
        <h2>{{ $heading }} ({{ $statement['counts'][$status] }})</h2>
        <ul>
            @foreach ($statement['criteria'][$status] as $criterion)
                <li>
                    <strong>{{ $criterion['id'] }}</strong> {{ $criterion['title'] }} <span class="text-sm text-gray-700">({{ $criterion['priority'] }})</span>
                    <span class="block text-sm text-gray-700">{{ $criterion['note'] }}</span>
                </li>
            @endforeach
        </ul>
    @endforeach

    <h2>Contact</h2>
    <p>Pour une question ou une remarque sur l'écoconception du site : <a href="{{ $reportUrl }}">signaler un problème sur GitHub</a>.</p>
</x-statement-page>
