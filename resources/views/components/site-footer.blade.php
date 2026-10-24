{{--
    On every page: the statements on the left (E1.5; the RGAA asks for the conformance status in the link), the report
    link on the right (E3.9), in the same tab (RGAA 13.2), its name saying that it leaves the site.
--}}
<footer class="flex shrink-0 flex-wrap justify-between gap-x-4 gap-y-1 border-t border-gray-300 px-4 py-2 text-xs text-gray-700">
    <ul class="flex flex-wrap gap-x-4 gap-y-1">
        <li><a href="{{ route('accessibility') }}" class="underline underline-offset-2 hover:no-underline">Accessibilité : {{ $accessibilityStatus }}</a></li>
        <li><a href="{{ route('ecodesign') }}" class="underline underline-offset-2 hover:no-underline">Écoconception</a></li>
    </ul>
    <a href="{{ $reportUrl }}" class="ml-auto underline underline-offset-2 hover:no-underline">Signaler un problème<span class="sr-only"> sur GitHub</span></a>
</footer>
