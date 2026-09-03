export type OnboardingStepKey =
    | 'delivery'
    | 'sender'
    | 'audience'
    | 'subscribers'
    | 'campaign'
    | 'automation';

export type OnboardingStep = {
    key: OnboardingStepKey;
    completed: boolean;
};

export type OnboardingChecklist = {
    completed: number;
    total: number;
    steps: OnboardingStep[];
};
