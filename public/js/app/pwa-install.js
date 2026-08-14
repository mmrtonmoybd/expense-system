let deferredInstallPrompt = null

const installAction = () => document.querySelector('[data-pwa-install-action]')

const isRunningAsInstalledApp = () =>
    window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true

const hasInstalledRelatedApp = async () => {
    if (!('getInstalledRelatedApps' in navigator)) {
        return false
    }

    try {
        const relatedApps = await navigator.getInstalledRelatedApps()

        return relatedApps.some((app) => app.platform === 'webapp')
    } catch {
        return false
    }
}

const hideInstallAction = () => {
    installAction()?.setAttribute('hidden', '')
}

const showInstallAction = async () => {
    const action = installAction()

    if (!action || !deferredInstallPrompt || isRunningAsInstalledApp() || (await hasInstalledRelatedApp())) {
        hideInstallAction()

        return
    }

    action.removeAttribute('hidden')
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    deferredInstallPrompt = event
    void showInstallAction()
})

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null
    hideInstallAction()
})

window.matchMedia('(display-mode: standalone)').addEventListener('change', ({ matches }) => {
    if (matches) {
        deferredInstallPrompt = null
        hideInstallAction()
    }
})

document.addEventListener('click', async (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-pwa-install-button]') : null

    if (!button || !deferredInstallPrompt) {
        return
    }

    button.disabled = true

    try {
        await deferredInstallPrompt.prompt()
        await deferredInstallPrompt.userChoice
    } finally {
        deferredInstallPrompt = null
        button.disabled = false
        hideInstallAction()
    }
})

document.addEventListener('livewire:navigated', () => {
    void showInstallAction()
})

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'))
}
