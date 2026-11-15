/*!
 * Knowbase chat widget loader.
 *
 * Websites add it with:
 *   <script src="https://YOUR-KNOWBASE/widget.js" data-key="kbw_..." async></script>
 *
 * It adds a floating button and a hidden iframe with the chat (/widget/{key}).
 * The button only appears once the iframe says "ready", so a disabled widget,
 * a wrong key or a website that isn't allowed (Content-Security-Policy: frame-ancestors)
 * shows nothing at all instead of a broken box.
 *
 * Plain JavaScript on purpose: it runs on other people's websites, as-is (no build step),
 * so it has no dependencies and puts everything inside a Shadow DOM, where the
 * website's CSS can't reach it and its CSS can't leak into the website.
 */
(function () {
    'use strict';

    const SOURCE = 'knowbase-widget';
    const KEY_PATTERN = /^kbw_[A-Za-z0-9]{32}$/;

    const script = document.currentScript;

    if (!script || window.KnowbaseWidget) {
        return; // not loaded as a normal script tag, or added twice
    }

    const key = script.getAttribute('data-key') || '';

    if (!KEY_PATTERN.test(key)) {
        console.warn('[Knowbase] The widget script needs a valid data-key.');

        return;
    }

    // The iframe lives on the same server as this script.
    const origin = new URL(script.src).origin;

    const ICON_CHAT =
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>';
    const ICON_CLOSE =
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

    const STYLES = `
        :host { all: initial; }

        .launcher {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 2147483000;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            padding: 0;
            border: 0;
            border-radius: 9999px;
            background: #171717;
            color: #fafafa;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            transition: transform 0.15s ease;
        }
        .launcher[hidden] { display: none; }
        .launcher:hover { transform: scale(1.05); }
        .launcher:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }

        .panel {
            position: fixed;
            right: 20px;
            bottom: 88px;
            z-index: 2147483000;
            width: 380px;
            height: min(600px, calc(100vh - 108px));
            height: min(600px, calc(100dvh - 108px));
            overflow: hidden;
            border-radius: 16px;
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.25);
            opacity: 0;
            transform: translateY(12px);
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0s linear 0.2s;
        }
        .panel.open {
            opacity: 1;
            transform: none;
            visibility: visible;
            pointer-events: auto;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* Phones: the chat takes the whole screen; its own X button closes it. */
        @media (max-width: 480px) {
            .panel {
                inset: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
            }
            .panel.open ~ .launcher { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .launcher, .panel, .panel.open { transition: none; }
        }
    `;

    function mount() {
        const host = document.createElement('div');
        host.id = 'knowbase-widget';

        // "closed": the website's scripts can't reach inside either.
        const root = host.attachShadow({ mode: 'closed' });

        const style = document.createElement('style');
        style.textContent = STYLES;

        const panel = document.createElement('div');
        panel.className = 'panel';

        const iframe = document.createElement('iframe');
        iframe.title = 'Chat';
        iframe.src = origin + '/widget/' + encodeURIComponent(key);

        const launcher = document.createElement('button');
        launcher.type = 'button';
        launcher.className = 'launcher';
        launcher.hidden = true; // until the iframe says "ready"

        let isOpen = false;

        function setOpen(open) {
            isOpen = open;
            panel.classList.toggle('open', open);
            launcher.innerHTML = open ? ICON_CLOSE : ICON_CHAT;
            launcher.setAttribute(
                'aria-label',
                open ? 'Close chat' : 'Open chat',
            );
            launcher.setAttribute('aria-expanded', String(open));

            if (open) {
                iframe.contentWindow.postMessage(
                    { source: SOURCE, type: 'open' },
                    origin,
                );
            } else {
                launcher.focus(); // focus was probably inside the chat: put it somewhere sensible
            }
        }

        launcher.addEventListener('click', function () {
            setOpen(!isOpen);
        });

        // Listen before the iframe exists, so its "ready" can't be missed.
        window.addEventListener('message', function (event) {
            if (
                event.origin !== origin ||
                event.source !== iframe.contentWindow
            ) {
                return; // only messages from our own iframe
            }

            const data = event.data;

            if (!data || data.source !== SOURCE) {
                return;
            }

            if (data.type === 'ready') {
                launcher.hidden = false;
            } else if (data.type === 'close' && isOpen) {
                setOpen(false);
            }
        });

        launcher.innerHTML = ICON_CHAT;
        launcher.setAttribute('aria-label', 'Open chat');
        launcher.setAttribute('aria-expanded', 'false');

        panel.appendChild(iframe);
        root.append(style, panel, launcher); // launcher after panel: needed by the phone CSS
        document.body.appendChild(host);

        // A small public API, e.g. <a onclick="KnowbaseWidget.open()">Chat with us</a>
        window.KnowbaseWidget = {
            open: function () {
                if (!launcher.hidden) {
                    setOpen(true);
                }
            },
            close: function () {
                if (isOpen) {
                    setOpen(false);
                }
            },
        };
    }

    // Claim the name right away, so a second copy of the script stops early.
    window.KnowbaseWidget = { open: function () {}, close: function () {} };

    if (document.body) {
        mount();
    } else {
        document.addEventListener('DOMContentLoaded', mount);
    }
})();
