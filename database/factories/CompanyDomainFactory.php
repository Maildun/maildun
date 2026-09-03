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
            'company_id' => Company::factory(),
            'domain' => fake()->unique()->domainName(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CompanyDomain $companyDomain): void {
            $companyDomain->updateQuietly([
                'team_id' => $companyDomain->company->team_id,
            ]);
        });
    }
}
