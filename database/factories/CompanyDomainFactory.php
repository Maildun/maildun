<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyDomain> */
class CompanyDomainFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'company_id' => fn (array $attributes): Factory => Company::factory()->state(['team_id' => $attributes['team_id']]),
            'domain' => fake()->unique()->domainName(),
        ];
    }

    /**
     * A domain always lives in its company's workspace, so a given company
     * decides the team before the row is saved.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CompanyDomain $companyDomain): void {
            $companyDomain->team_id = Company::query()->whereKey($companyDomain->company_id)->value('team_id');
        });
    }
}
