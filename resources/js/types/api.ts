export type ApiWarning = {
    code: string;
    message: string;
};

export type ApiFailureBody = {
    message: string;
    code: string;
    warnings?: ApiWarning[];
};

export type ApiValidationFailureBody = {
    message: string;
    errors: Record<string, string[]>;
};
