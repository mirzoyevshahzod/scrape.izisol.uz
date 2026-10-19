{{--
    Har bir sahifaning <head> qismiga qo'shiladi:
    1) Barcha same-origin POST/PUT/DELETE fetch so'rovlariga CSRF token qo'shadi
       (/api/* endpoint'lar endi session + CSRF bilan himoyalangan).
    2) Sessiya tugagan bo'lsa (401/419 yoki /login'ga redirect), login sahifasiga o'tkazadi.
--}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    (function () {
        const token = @json(csrf_token());
        const loginUrl = @json(route('login'));
        const originalFetch = window.fetch;

        window.fetch = function (input, init) {
            init = init || {};
            const isRequest = input instanceof Request;
            const method = (init.method || (isRequest ? input.method : 'GET')).toUpperCase();
            const url = new URL(isRequest ? input.url : input, window.location.href);

            if (method !== 'GET' && method !== 'HEAD' && url.origin === window.location.origin) {
                const headers = new Headers(init.headers || (isRequest ? input.headers : undefined));

                if (!headers.has('X-CSRF-TOKEN')) {
                    headers.set('X-CSRF-TOKEN', token);
                }

                init.headers = headers;
            }

            return originalFetch.call(this, input, init).then(function (response) {
                const redirectedToLogin = response.redirected && new URL(response.url).pathname === new URL(loginUrl).pathname;

                if (response.status === 401 || response.status === 419 || redirectedToLogin) {
                    window.location.href = loginUrl;
                }

                return response;
            });
        };
    })();
</script>
