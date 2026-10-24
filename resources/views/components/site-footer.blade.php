{{-- On every page (E1.5): the RGAA asks for the conformance status in the link to the accessibility statement. --}}
<footer class="shrink-0 border-t border-gray-300 px-4 py-2 text-xs text-gray-700">
    <ul class="flex flex-wrap gap-x-4 gap-y-1">
        <li><a href="{{ route('accessibility') }}" class="underline underline-offset-2 hover:no-underline">Accessibilité : {{ $accessibilityStatus }}</a></li>
        <li><a href="{{ route('ecodesign') }}" class="underline underline-offset-2 hover:no-underline">Écoconception</a></li>
    </ul>
</footer>
