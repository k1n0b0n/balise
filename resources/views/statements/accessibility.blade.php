{{-- On the RGAA's model of an accessibility statement, from the self-audit in resources/audits/rgaa.json (E1.5). --}}
<x-statement-page title="Déclaration d'accessibilité">
    <p>balise vise la conformité au référentiel général d'amélioration de l'accessibilité (RGAA), version 4.1.2, niveau AA. Cette déclaration s'applique au site {{ url('/') }}.</p>

    <h2>État de conformité</h2>
    <p>balise est <strong>{{ $statement['status'] }}</strong> avec le RGAA 4.1.2{{ $statement['failing'] === [] ? '' : ', en raison des non-conformités énumérées ci-dessous' }}.</p>

    <h2>Résultats des tests</h2>
    <p>L'auto-évaluation, réalisée par le mainteneur du site et non par un auditeur indépendant, révèle que {{ $statement['rate'] }}&nbsp;% des critères applicables du RGAA 4.1.2 sont respectés.</p>
    <ul>
        <li>Critères conformes : {{ $statement['counts']['C'] }}</li>
        <li>Critères non conformes : {{ $statement['counts']['NC'] }}</li>
        <li>Critères non applicables : {{ $statement['counts']['NA'] }}</li>
    </ul>

    <h2>Contenus non accessibles</h2>
    <h3>Non-conformités</h3>
    @if ($statement['failing'] === [])
        <p>Aucune non-conformité n'a été relevée.</p>
    @else
        <ul>
            @foreach ($statement['failing'] as $criterion)
                <li>Critère {{ $criterion['id'] }} : {{ $criterion['title'] }}</li>
            @endforeach
        </ul>
    @endif
    <h3>Dérogations pour charge disproportionnée</h3>
    <p>Aucune.</p>
    <h3>Alternatives proposées</h3>
    <p>La carte a un équivalent textuel : la liste des véhicules, qui la suit dans la page, est rendue par le serveur et lisible sans JavaScript. Chaque véhicule de la liste ouvre sa fenêtre sur la carte. Un bouton Pause arrête les mises à jour de la carte et de la liste.</p>

    <h2>Établissement de cette déclaration</h2>
    <p>Cette déclaration a été établie le {{ \Illuminate\Support\Carbon::parse($statement['date'])->translatedFormat('j F Y') }}.</p>
    <h3>Technologies utilisées</h3>
    <ul>
        <li>HTML</li>
        <li>CSS</li>
        <li>JavaScript</li>
        <li>WAI-ARIA</li>
    </ul>
    <h3>Environnement de test</h3>
    <ul>
        @foreach ($statement['environment'] as $environment)
            <li>{{ $environment }}</li>
        @endforeach
    </ul>
    <h3>Outils pour évaluer l'accessibilité</h3>
    <ul>
        @foreach ($statement['tools'] as $tool)
            <li>{{ $tool }}</li>
        @endforeach
    </ul>
    <h3>Pages du site ayant fait l'objet de la vérification de conformité</h3>
    <ul>
        @foreach ($statement['pages'] as $page)
            <li>{{ $page['name'] }} : {{ rtrim(url('/'), '/').$page['path'] }}</li>
        @endforeach
    </ul>

    <h2>Retour d'information et contact</h2>
    <p>Si vous n'arrivez pas à accéder à un contenu ou à un service, signalez-le pour être orienté vers une alternative accessible ou obtenir le contenu sous une autre forme : <a href="{{ $reportUrl }}">signaler un problème sur GitHub</a>.</p>

    <h2>Voies de recours</h2>
    <p>Cette procédure est à utiliser dans le cas suivant : vous avez signalé un défaut d'accessibilité qui vous empêche d'accéder à un contenu ou à un des services du site et vous n'avez pas obtenu de réponse satisfaisante.</p>
    <ul>
        <li><a href="https://formulaire.defenseurdesdroits.fr/">Écrire un message au Défenseur des droits</a></li>
        <li><a href="https://www.defenseurdesdroits.fr/carte-des-delegues">Contacter le délégué du Défenseur des droits dans votre région</a></li>
        <li>Envoyer un courrier par la poste (gratuit, ne pas mettre de timbre) : Défenseur des droits, Libre réponse 71120, 75342 Paris CEDEX 07</li>
    </ul>
</x-statement-page>
