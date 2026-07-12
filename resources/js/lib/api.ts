/**
 * Thin fetch wrapper for the /api/v1 dashboard endpoints. These are
 * session-authenticated via Sanctum's stateful SPA mode (see
 * EnsureFrontendRequestsAreStateful in bootstrap/app.php), so mutating
 * requests must carry the XSRF-TOKEN cookie back as a header -- Laravel's
 * VerifyCsrfToken equivalent for the API guard checks it there.
 */

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');

    if (method !== 'GET' && method !== 'HEAD') {
        const token = readCookie('XSRF-TOKEN');
        if (token) headers.set('X-XSRF-TOKEN', token);
        if (!(options.body instanceof FormData)) {
            headers.set('Content-Type', 'application/json');
        }
    }

    const response = await fetch(`/api/v1${path}`, {
        ...options,
        method,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`API ${method} ${path} failed: ${response.status} ${await response.text()}`);
    }

    return response.json() as Promise<T>;
}

export const api = {
    get: <T>(path: string) => request<T>(path),
    post: <T>(path: string, body?: unknown) =>
        request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
    patch: <T>(path: string, body?: unknown) =>
        request<T>(path, { method: 'PATCH', body: body ? JSON.stringify(body) : undefined }),
};
