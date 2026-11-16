export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export type TokenUsage = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
};

/** Prices are whole nanodollars (1e-9 USD). */
export type Generation = {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimated_price_nanos: number;
    max_price_nanos: number;
    price_nanos: number | null;
    usage: TokenUsage | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
};

export type GenerationOptions = {
    sizes: string[];
    qualities: string[];
    maxPromptLength: number;
    maxReferenceImages: number;
};

export type Quote = {
    estimated_price_nanos: number;
    max_price_nanos: number;
};

export type Credits = {
    balance: number;
    reserved: number;
    available: number;
};

export type Paginated<T> = {
    data: T[];
    links: {
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
};
