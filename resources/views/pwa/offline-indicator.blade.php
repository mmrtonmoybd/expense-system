<div
    x-data="{ offline: ! navigator.onLine }"
    x-init="
        window.addEventListener('online', () => (offline = false))
        window.addEventListener('offline', () => (offline = true))
    "
    x-show="offline"
    x-cloak
    class="bg-danger-600 fixed inset-x-3 bottom-[max(1rem,env(safe-area-inset-bottom))] z-[100] rounded-xl px-4 py-3 text-center text-sm font-semibold text-white shadow-lg"
>
    Offline. Financial transactions cannot be posted until connection returns.
</div>
