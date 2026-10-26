import { normalise, type Station, type StationCatalogue, type StationLayer } from './stations';

/**
 * The station search (E7.1), the text equivalent of the stations on the map: the form works without JavaScript (the
 * server lists the matches); here it suggests the names, and centres the map on the station found, saying so.
 */
export function showStationSearch(form: HTMLFormElement, catalogue: StationCatalogue, layer: Promise<StationLayer>): void {
    const input = form.querySelector<HTMLInputElement>('input[name="arret"]');
    const suggestions = form.querySelector<HTMLDataListElement>('datalist');
    const status = form.querySelector<HTMLElement>('[data-station-status]');

    if (input === null || status === null) {
        return;
    }

    let stations: Station[] = [];

    function load(): Promise<Station[]> {
        return catalogue.load().then((loaded) => {
            if (stations.length === 0 && suggestions !== null) {
                // One suggestion per name: several stations may share one, in different towns or as lone platforms.
                const names = [...new Set(loaded.map((station) => station.name))].sort((a, b) => a.localeCompare(b, 'fr'));
                suggestions.replaceChildren(...names.map((name) => Object.assign(document.createElement('option'), { value: name })));
            }
            stations = loaded;

            return loaded;
        });
    }

    input.addEventListener('focus', () => void load().catch(() => undefined), { once: true });

    // A suggestion chosen shows its station at once, without the button. Browsers report the choice as a replacement
    // of the text (or, for some, as an input event without a type); typing the same name letter by letter does not.
    input.addEventListener('input', (event) => {
        const chosen = !(event instanceof InputEvent) || event.inputType === 'insertReplacementText';

        if (chosen && stations.some((station) => station.name === input.value)) {
            form.requestSubmit();
        }
    });

    // Only the station found goes without saying on screen: the map shows it. The other answers are seen too.
    const say = (text: string, visible: boolean): void => {
        status.classList.toggle('sr-only', !visible);
        status.textContent = text;
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const asked = normalise(input.value);

        if (asked === '') {
            say('Saisissez le nom d’un arrêt.', true);

            return;
        }

        load().then(
            (loaded) => {
                // The exact name first, then the first name that contains what was typed.
                const found = loaded.find((station) => normalise(station.name) === asked) ?? loaded.find((station) => normalise(station.name).includes(asked));

                if (found === undefined) {
                    say(`Aucun arrêt ne correspond à « ${input.value.trim()} ».`, true);

                    return;
                }

                // A station found before the map is ready waits for it.
                void layer.then((shown) => {
                    shown.open(found);
                    say(`Carte centrée sur l’arrêt ${found.name}.`, false);
                });
            },
            () => say('La liste des arrêts n’a pas pu être chargée : réessayez plus tard.', true),
        );
    });
}
