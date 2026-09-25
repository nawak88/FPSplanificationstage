<x-filament-panels::page>
    @php
        $oldBesoins = old('besoins');

        if (! is_array($oldBesoins) || $oldBesoins === []) {
            $oldBesoins = [[
                'stage_id' => '',
                'type_periode' => 'dates_fixes',
                'date_debut_souhaitee' => '',
                'date_fin_souhaitee' => '',
                'nombre_stagiaires' => 1,
                'commentaire' => '',
            ]];
        }
    @endphp

    <div style="margin-bottom:1rem;">
        <x-filament::button tag="a" href="{{ $planningUrl }}" color="gray">
            ← Retour au planning des formations
        </x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">Expression de besoin en stage</x-slot>
        <div>
            Ce formulaire permet de transmettre un ou plusieurs besoins de formation aux gestionnaires.
        </div>
    </x-filament::section>

    @include('fpsplanificationstage::filament.public._errors')

    <form
        method="POST"
        action="{{ route('fpsplanificationstage.public.besoin.store', [], false) }}"
        id="form-besoins-multiples"
    >
        @csrf

        <x-filament::section>
            <x-slot name="heading">Demandeur</x-slot>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Bâtiment / unité *</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="demandeur"
                            name="demandeur"
                            list="unites-demandeur"
                            value="{{ old('demandeur', $demandeur) }}"
                            autocomplete="off"
                            required
                        />
                    </x-filament::input.wrapper>
                    <datalist id="unites-demandeur">
                        @foreach ($unites as $unite)
                            <option value="{{ $unite }}"></option>
                        @endforeach
                    </datalist>
                    <p style="margin-top:.35rem;color:#64748b;font-size:.85rem;">
                        Commencez à saisir le libellé de l’unité, puis choisissez-la dans la liste.
                    </p>
                </div>

                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Nom du contact *</label>
                    <x-filament::input.wrapper>
                        <x-filament::input name="contact_nom" value="{{ old('contact_nom') }}" />
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">E-mail *</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="email" name="contact_email" value="{{ old('contact_email') }}" />
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Téléphone</label>
                    <x-filament::input.wrapper>
                        <x-filament::input name="contact_telephone" value="{{ old('contact_telephone') }}" />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <div id="besoins-container" style="display:grid;gap:1rem;margin-top:1rem;">
            @foreach ($oldBesoins as $index => $besoin)
                <div class="besoin-stage-card" data-besoin-index="{{ $index }}">
                    <x-filament::section>
                        <x-slot name="heading">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;width:100%;">
                                <span>
                                    Stage demandé
                                    <span class="besoin-numero">{{ count($oldBesoins) > 1 ? $loop->iteration : '' }}</span>
                                </span>

                                <button
                                    type="button"
                                    class="remove-besoin-stage"
                                    title="Supprimer ce besoin"
                                    style="
                                        display:{{ count($oldBesoins) > 1 ? 'inline-flex' : 'none' }};
                                        align-items:center;
                                        justify-content:center;
                                        width:2rem;
                                        height:2rem;
                                        border-radius:.5rem;
                                        border:1px solid #d1d5db;
                                        background:white;
                                        cursor:pointer;
                                        font-size:1.1rem;
                                    "
                                >×</button>
                            </div>
                        </x-slot>

                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
                            <div style="grid-column:1/-1;">
                                <label style="display:block;font-weight:700;margin-bottom:.35rem;">Stage *</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select name="besoins[{{ $index }}][stage_id]">
                                        <option value="">Sélectionner un stage</option>
                                        @foreach ($stages as $stage)
                                            <option
                                                value="{{ $stage->id }}"
                                                @selected((string) ($besoin['stage_id'] ?? '') === (string) $stage->id)
                                            >
                                                {{ $stage->libelle_court }}
                                                {{ $stage->libelle_long ? ' — ' . $stage->libelle_long : '' }}
                                            </option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </div>

                            <div data-period-type style="grid-column:1/-1;">
                                <label style="display:block;font-weight:700;margin-bottom:.35rem;">Quand ce stage peut-il être planifié ? *</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select name="besoins[{{ $index }}][type_periode]">
                                        <option value="dates_fixes" @selected(($besoin['type_periode'] ?? 'dates_fixes') === 'dates_fixes')>
                                            Date de début imposée
                                        </option>
                                        <option value="plage" @selected(($besoin['type_periode'] ?? '') === 'plage')>
                                            Période disponible
                                        </option>
                                    <option value="plage_demarrage" @selected(($besoin['type_periode'] ?? '') === 'plage_demarrage')>
                                        Période de démarrage
                                    </option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <div data-period-help style="margin-top:.45rem;font-size:.875rem;color:#64748b;"></div>
                            </div>

                            <div data-period-field="start">
                                <label data-period-label="start" style="display:block;font-weight:700;margin-bottom:.35rem;">Date de début imposée *</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="date"
                                        name="besoins[{{ $index }}][date_debut_souhaitee]"
                                        value="{{ $besoin['date_debut_souhaitee'] ?? '' }}"
                                    />
                                </x-filament::input.wrapper>
                            </div>

                            <div data-period-field="end">
                                <label data-period-label="end" style="display:block;font-weight:700;margin-bottom:.35rem;">Au *</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="date"
                                        name="besoins[{{ $index }}][date_fin_souhaitee]"
                                        value="{{ $besoin['date_fin_souhaitee'] ?? '' }}"
                                    />
                                </x-filament::input.wrapper>
                            </div>


                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:.35rem;">Nombre de stagiaires *</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="number"
                                        min="1"
                                        max="999"
                                        name="besoins[{{ $index }}][nombre_stagiaires]"
                                        value="{{ $besoin['nombre_stagiaires'] ?? 1 }}"
                                    />
                                </x-filament::input.wrapper>
                            </div>

                            <div style="grid-column:1/-1;">
                                <label style="display:block;font-weight:700;margin-bottom:.35rem;">Commentaire</label>
                                <textarea
                                    name="besoins[{{ $index }}][commentaire]"
                                    rows="4"
                                    style="width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.7rem;"
                                >{{ $besoin['commentaire'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </x-filament::section>
                </div>
            @endforeach
        </div>

        <div style="display:flex;align-items:center;gap:.75rem;margin-top:1rem;flex-wrap:wrap;">
            <button
                type="button"
                id="add-besoin-stage"
                title="Ajouter un autre besoin en stage"
                style="
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    gap:.45rem;
                    min-height:2.25rem;
                    padding:.45rem .8rem;
                    border-radius:.55rem;
                    border:1px solid #2563eb;
                    background:#fff;
                    color:#2563eb;
                    font-weight:700;
                    cursor:pointer;
                "
            >
                <span style="font-size:1.25rem;line-height:1;">+</span>
                Ajouter un stage
            </button>

            <x-filament::button type="submit">
                Transmettre
                <span id="besoins-submit-count"></span>
            </x-filament::button>
        </div>
    </form>

    <template id="besoin-stage-template">
        <div class="besoin-stage-card" data-besoin-index="__INDEX__">
            <section
                style="
                    border-radius:.75rem;
                    background:white;
                    border:1px solid #e5e7eb;
                    overflow:hidden;
                "
            >
                <div style="padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                        <div style="font-weight:700;">
                            Stage demandé <span class="besoin-numero"></span>
                        </div>

                        <button
                            type="button"
                            class="remove-besoin-stage"
                            title="Supprimer ce besoin"
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                width:2rem;
                                height:2rem;
                                border-radius:.5rem;
                                border:1px solid #d1d5db;
                                background:white;
                                cursor:pointer;
                                font-size:1.1rem;
                            "
                        >×</button>
                    </div>
                </div>

                <div style="padding:1.5rem;">
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-weight:700;margin-bottom:.35rem;">Stage *</label>
                            <select
                                name="besoins[__INDEX__][stage_id]"
                                style="width:100%;min-height:2.5rem;border:1px solid #d1d5db;border-radius:.5rem;padding:.45rem .7rem;background:white;"
                            >
                                <option value="">Sélectionner un stage</option>
                                @foreach ($stages as $stage)
                                    <option value="{{ $stage->id }}">
                                        {{ $stage->libelle_court }}
                                        {{ $stage->libelle_long ? ' — ' . $stage->libelle_long : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div data-period-type style="grid-column:1/-1;">
                            <label style="display:block;font-weight:700;margin-bottom:.35rem;">Quand ce stage peut-il être planifié ? *</label>
                            <select
                                name="besoins[__INDEX__][type_periode]"
                                style="width:100%;min-height:2.5rem;border:1px solid #d1d5db;border-radius:.5rem;padding:.45rem .7rem;background:white;"
                            >
                                <option value="dates_fixes">Date de début imposée</option>
                                <option value="plage">Période disponible</option>
                                <option value="plage_demarrage">Période de démarrage</option>
                            </select>
                            <div data-period-help style="margin-top:.45rem;font-size:.875rem;color:#64748b;"></div>
                        </div>

                        <div data-period-field="start">
                            <label data-period-label="start" style="display:block;font-weight:700;margin-bottom:.35rem;">Date de début imposée *</label>
                            <input
                                type="date"
                                name="besoins[__INDEX__][date_debut_souhaitee]"
                                style="width:100%;min-height:2.5rem;border:1px solid #d1d5db;border-radius:.5rem;padding:.45rem .7rem;"
                            />
                        </div>

                        <div data-period-field="end">
                            <label data-period-label="end" style="display:block;font-weight:700;margin-bottom:.35rem;">Au *</label>
                            <input
                                type="date"
                                name="besoins[__INDEX__][date_fin_souhaitee]"
                                style="width:100%;min-height:2.5rem;border:1px solid #d1d5db;border-radius:.5rem;padding:.45rem .7rem;"
                            />
                        </div>


                        <div>
                            <label style="display:block;font-weight:700;margin-bottom:.35rem;">Nombre de stagiaires *</label>
                            <input
                                type="number"
                                min="1"
                                max="999"
                                value="1"
                                name="besoins[__INDEX__][nombre_stagiaires]"
                                style="width:100%;min-height:2.5rem;border:1px solid #d1d5db;border-radius:.5rem;padding:.45rem .7rem;"
                            />
                        </div>

                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-weight:700;margin-bottom:.35rem;">Commentaire</label>
                            <textarea
                                name="besoins[__INDEX__][commentaire]"
                                rows="4"
                                style="width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.7rem;"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('besoins-container');
            const template = document.getElementById('besoin-stage-template');
            const addButton = document.getElementById('add-besoin-stage');
            const submitCount = document.getElementById('besoins-submit-count');

            let nextIndex = (() => {
                const indexes = Array.from(
                    container.querySelectorAll('.besoin-stage-card')
                )
                    .map(card => Number(card.dataset.besoinIndex))
                    .filter(value => Number.isFinite(value));

                return indexes.length ? Math.max(...indexes) + 1 : 0;
            })();

            const cards = () =>
                Array.from(
                    container.querySelectorAll('.besoin-stage-card')
                );

            function refresh() {
                const items = cards();

                items.forEach((card, index) => {
                    const numero = card.querySelector('.besoin-numero');
                    const remove = card.querySelector('.remove-besoin-stage');

                    if (numero) {
                        numero.textContent =
                            items.length > 1
                                ? String(index + 1)
                                : '';
                    }

                    if (remove) {
                        remove.style.display =
                            items.length > 1
                                ? 'inline-flex'
                                : 'none';
                    }
                });

                if (submitCount) {
                    submitCount.textContent =
                        items.length > 1
                            ? ` (${items.length} besoins)`
                            : '';
                }
            }

            function bindRemove(button) {
                button.addEventListener('click', function () {
                    const item = button.closest('.besoin-stage-card');

                    if (item && cards().length > 1) {
                        item.remove();
                        refresh();
                    }
                });
            }

            container
                .querySelectorAll('.remove-besoin-stage')
                .forEach(bindRemove);

            addButton.addEventListener('click', function () {
                const html = template.innerHTML.replaceAll(
                    '__INDEX__',
                    String(nextIndex++)
                );

                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();

                const item = wrapper.firstElementChild;
                container.appendChild(item);

                const remove = item.querySelector('.remove-besoin-stage');

                if (remove) {
                    bindRemove(remove);
                }

                refresh();

                item.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });

            refresh();
        });
    </script>

    <script>
        /* BESOINS_MODES_PLANIFICATION_V1 */
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('besoins-container');

            if (! container) {
                return;
            }

            function syncCard(card) {
                const typeSelect = card.querySelector('select[name$="[type_periode]"]');
                const startLabel = card.querySelector('[data-period-label="start"]');
                const endField = card.querySelector('[data-period-field="end"]');
                const endLabel = card.querySelector('[data-period-label="end"]');
                const help = card.querySelector('[data-period-help]');

                if (! typeSelect || ! endField) {
                    return;
                }

                const type = typeSelect.value;
                const endInput = endField.querySelector('input');

                if (type === 'dates_fixes') {
                    if (startLabel) {
                        startLabel.textContent = 'Date de début imposée *';
                    }

                    endField.style.display = 'none';

                    if (endInput) {
                        endInput.required = false;
                        endInput.value = '';
                    }

                    if (help) {
                        help.textContent = 'Le stage commence exactement à la date indiquée. Sa fin est calculée automatiquement selon sa durée.';
                    }

                    return;
                }

                endField.style.display = '';

                if (endInput) {
                    endInput.required = true;
                }

                if (type === 'plage') {
                    if (startLabel) {
                        startLabel.textContent = 'Disponible du *';
                    }

                    if (endLabel) {
                        endLabel.textContent = 'Au *';
                    }

                    if (help) {
                        help.textContent = 'Le stage doit être entièrement réalisé entre ces deux dates.';
                    }

                    return;
                }

                if (startLabel) {
                    startLabel.textContent = 'Le stage peut débuter entre le *';
                }

                if (endLabel) {
                    endLabel.textContent = 'Et le *';
                }

                if (help) {
                    help.textContent = 'Le stage peut commencer n’importe quel jour dans cette période. Il peut se terminer après.';
                }
            }

            function syncAll() {
                container
                    .querySelectorAll('.besoin-stage-card')
                    .forEach(syncCard);
            }

            container.addEventListener('change', function (event) {
                const target = event.target;

                if (! target || ! target.matches('select[name$="[type_periode]"]')) {
                    return;
                }

                const card = target.closest('.besoin-stage-card');

                if (card) {
                    syncCard(card);
                }
            });

            new MutationObserver(syncAll).observe(
                container,
                { childList: true }
            );

            syncAll();
        });
    </script>

</x-filament-panels::page>
