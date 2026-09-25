<x-filament-panels::page>
    <div style="margin-bottom:1rem;">
        <x-filament::button tag="a" href="{{ $sessionUrl }}" color="gray">← Retour à la session</x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">Inscription au stage</x-slot>
        <div style="font-size:1.15rem;font-weight:800;">{{ $session->stage?->libelle_court }}</div>
        <div>Session : {{ $session->code_session }}</div>
        <div>Du {{ $session->debut?->format('d/m/Y H:i') }} au {{ $session->fin?->format('d/m/Y H:i') }}</div>
        <div>Salle : {{ $session->salle?->nom ?? 'Non renseignée' }}</div>
    </x-filament::section>

    @include('fpsplanificationstage::filament.public._errors')

    <form method="POST" action="{{ route('fpsplanificationstage.public.inscription.store', ['session' => $session->id], false) }}">
        @csrf

        <x-filament::section>
            <x-slot name="heading">Vos informations</x-slot>

            <p style="margin-bottom:1rem;color:#475569;">
                Votre nom, votre prénom et votre adresse électronique
                proviennent de votre connexion MindefConnect.
            </p>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
                @foreach ([
                    ['nom','Nom *','text'],
                    ['prenom','Prénom *','text'],
                    ['matricule','Matricule','text'],
                    ['nid','NID','text'],
                    ['unite','Bâtiment / unité *','text'],
                    ['email','E-mail *','email'],
                ] as [$name,$label,$type])
                    <div>
                        <label style="display:block;font-weight:700;margin-bottom:.35rem;">{{ $label }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="{{ $type }}"
                                name="{{ $name }}"
                                value="{{ old($name, $identity[$name] ?? null) }}"
                                :readonly="in_array($name, ['nom', 'prenom', 'email'], true)"
                            />
                        </x-filament::input.wrapper>
                    </div>
                @endforeach

                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Grade</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="grade">
                            <option value="">Sélectionner un grade</option>
                            @foreach ($grades as $grade)
                                <option
                                    value="{{ $grade->libelle_court }}"
                                    @selected(old('grade', $identity['grade'] ?? null) === $grade->libelle_court)
                                >
                                    {{ $grade->libelle_long ?: $grade->libelle_court }}
                                    @if ($grade->libelle_long && $grade->libelle_long !== $grade->libelle_court)
                                        — {{ $grade->libelle_court }}
                                    @endif
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Spécialité</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="specialite">
                            <option value="">Sélectionner une spécialité</option>
                            @foreach ($specialites as $specialite)
                                <option
                                    value="{{ $specialite->libelle_court }}"
                                    @selected(old('specialite', $identity['specialite'] ?? null) === $specialite->libelle_court)
                                >
                                    {{ $specialite->libelle_long ?: $specialite->libelle_court }}
                                    @if ($specialite->libelle_long && $specialite->libelle_long !== $specialite->libelle_court)
                                        — {{ $specialite->libelle_court }}
                                    @endif
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Brevet</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="brevet">
                            <option value="">Sélectionner un brevet</option>
                            @foreach ($brevets as $brevet)
                                <option
                                    value="{{ $brevet->libelle_court }}"
                                    @selected(old('brevet', $identity['brevet'] ?? null) === $brevet->libelle_court)
                                >
                                    {{ $brevet->libelle_long ?: $brevet->libelle_court }}
                                    @if ($brevet->libelle_long && $brevet->libelle_long !== $brevet->libelle_court)
                                        — {{ $brevet->libelle_court }}
                                    @endif
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Pré-requis</x-slot>

            @forelse ($session->stage?->prerequis ?? [] as $prerequis)
                <label style="display:flex;gap:.6rem;align-items:flex-start;margin-bottom:.75rem;">
                    <input type="checkbox" name="prerequis[{{ $prerequis->id }}]" value="1"
                           @checked(old('prerequis.' . $prerequis->id))>
                    <span>
                        <strong>{{ $prerequis->libelle ?? $prerequis->nom ?? 'Pré-requis' }}</strong>
                        @if ($prerequis->obligatoire) — obligatoire @endif
                    </span>
                </label>
            @empty
                <div>Aucun pré-requis déclaré pour ce stage.</div>
            @endforelse

            <div style="margin-top:1rem;">
                <label style="display:flex;gap:.6rem;align-items:center;">
                    <input type="checkbox" name="demande_derogation" value="1" @checked(old('demande_derogation'))>
                    <strong>Demander une dérogation si un pré-requis obligatoire n'est pas rempli</strong>
                </label>
            </div>

            <div style="margin-top:1rem;">
                <label style="display:block;font-weight:700;margin-bottom:.35rem;">Motif de la dérogation</label>
                <textarea name="derogation_motif" rows="5" style="width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.7rem;">{{ old('derogation_motif') }}</textarea>
            </div>
        </x-filament::section>

        <div style="margin-top:1rem;">
            <x-filament::button type="submit">Envoyer ma candidature</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
