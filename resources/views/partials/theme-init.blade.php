{{--
    Mavzuni (yorug'/qorong'i) sahifa chizilishidan oldin qo'llaydi — oq
    "miltillash" bo'lmasligi uchun <head> ichida, CSS'dan oldin turishi kerak.
    Foydalanuvchi tanlovi localStorage'da; tanlov bo'lmasa tizim sozlamasi.
--}}
<script>
    (function () {
        let theme = null;
        try { theme = localStorage.getItem('theme'); } catch (e) {}
        if (theme !== 'light' && theme !== 'dark') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
</script>
