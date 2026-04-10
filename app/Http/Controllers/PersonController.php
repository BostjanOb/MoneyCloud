<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Osebe', [
            'people' => Person::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Person $person): array => $this->transformPerson($person))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return $this->formPage();
    }

    public function edit(Person $person): Response
    {
        return $this->formPage($person);
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        Person::query()->create($request->validated());

        return redirect()->route('people.index');
    }

    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($request->validated());

        return redirect()->route('people.index');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $person->update(['is_active' => false]);

        return redirect()->route('people.index');
    }

    private function formPage(?Person $person = null): Response
    {
        return Inertia::render('OsebeForm', [
            'person' => $person ? $this->transformPerson($person) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function transformPerson(Person $person): array
    {
        return [
            'id' => $person->id,
            'slug' => $person->slug,
            'name' => $person->name,
            'is_active' => $person->is_active,
            'sort_order' => $person->sort_order,
        ];
    }
}
