/**
 * Nebula Toast — Auto-converts Magento admin messages into slide-down toasts
 * with timer bar, pause on hover, and manual pause button.
 */
(() => {
    "use strict";

    const TOAST_DURATION = 5000; // ms
    const ANIMATION_DURATION = 300; // ms

    // Type → style mapping
    const TYPE_STYLES = {
        success: {
            bg: "#16a34a",
            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
        },
        error: {
            bg: "#dc2626",
            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>',
        },
        warning: {
            bg: "#d97706",
            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
        },
        notice: {
            bg: "#2563eb",
            icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>',
        },
    };

    const createToastContainer = () => {
        let container = document.getElementById("nebula-toast-container");
        if (container) return container;

        container = document.createElement("div");
        container.id = "nebula-toast-container";
        container.setAttribute("role", "region");
        container.setAttribute("aria-label", "Notifications");
        container.style.cssText =
            "position:fixed;top:0;left:0;right:0;z-index:99999;display:flex;flex-direction:column;align-items:center;pointer-events:none;";
        const landmarkHost =
            document.querySelector(
                'main[role="main"], main, [role="main"], [aria-label="Main Content"]',
            ) || document.body;
        landmarkHost.appendChild(container);
        return container;
    };

    const createToast = (type, message) => {
        const style = TYPE_STYLES[type] || TYPE_STYLES.notice;
        const container = createToastContainer();

        const toast = document.createElement("div");
        toast.style.cssText =
            "pointer-events:auto;width:100%;max-width:600px;margin-top:8px;transform:translateY(-100%);opacity:0;transition:transform " +
            ANIMATION_DURATION +
            "ms ease-out, opacity " +
            ANIMATION_DURATION +
            "ms ease-out;";

        toast.innerHTML =
            "" +
            '<div style="background:' +
            style.bg +
            ';color:#f8fafc !important;border-radius:12px;padding:0;overflow:hidden;box-shadow:0 10px 25px -5px rgba(0,0,0,.2),0 8px 10px -6px rgba(0,0,0,.1);">' +
            '  <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;">' +
            '    <svg style="width:20px;height:20px;color:#f8fafc !important;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
            style.icon +
            "</svg>" +
            '    <span style="flex:1;color:#f8fafc !important;font-size:14px;font-weight:600;line-height:1.4;">' +
            message +
            "</span>" +
            '    <button class="nebula-toast-pause" title="Pause" style="color:rgba(255,255,255,.7);cursor:pointer;background:none;border:none;padding:2px;display:flex;flex-shrink:0;transition:color .15s;">' +
            '      <svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>' +
            "    </button>" +
            '    <button class="nebula-toast-close" title="Close" style="color:rgba(255,255,255,.7);cursor:pointer;background:none;border:none;padding:2px;display:flex;flex-shrink:0;transition:color .15s;">' +
            '      <svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>' +
            "    </button>" +
            "  </div>" +
            '  <div class="nebula-toast-timer" style="height:3px;background:rgba(255,255,255,.4);border-radius:0 0 12px 12px;">' +
            '    <div class="nebula-toast-timer-bar" style="height:100%;background:rgba(255,255,255,.8);border-radius:0 0 12px 12px;width:100%;transition:width linear;"></div>' +
            "  </div>" +
            "</div>";

        container.appendChild(toast);

        // Add hover handlers for buttons (replace inline handlers)
        const hoverButtons = toast.querySelectorAll(
            ".nebula-toast-pause, .nebula-toast-close",
        );
        hoverButtons.forEach((btn) => {
            btn.addEventListener("mouseover", () => {
                btn.style.color = "white";
            });
            btn.addEventListener("mouseout", () => {
                btn.style.color = "rgba(255,255,255,.7)";
            });
        });

        // Slide in
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.style.transform = "translateY(0)";
                toast.style.opacity = "1";
            });
        });

        // Timer
        const timerBar = toast.querySelector(".nebula-toast-timer-bar");
        let paused = false;
        let remaining = TOAST_DURATION;
        let startTime = Date.now();

        // Start the timer bar animation
        timerBar.style.transitionDuration = TOAST_DURATION + "ms";
        requestAnimationFrame(() => {
            timerBar.style.width = "0%";
        });

        const dismiss = () => {
            toast.style.transform = "translateY(-100%)";
            toast.style.opacity = "0";
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, ANIMATION_DURATION);
        };

        // Auto-dismiss timer
        let timer = setTimeout(dismiss, TOAST_DURATION);

        // Pause on hover
        toast.addEventListener("mouseenter", () => {
            if (!paused) {
                remaining -= Date.now() - startTime;
                clearTimeout(timer);
                timerBar.style.transitionDuration = "0ms";
                timerBar.style.width = (remaining / TOAST_DURATION) * 100 + "%";
            }
        });

        toast.addEventListener("mouseleave", () => {
            if (!paused) {
                startTime = Date.now();
                timerBar.style.transitionDuration = remaining + "ms";
                requestAnimationFrame(() => {
                    timerBar.style.width = "0%";
                });
                timer = setTimeout(dismiss, remaining);
            }
        });

        // Pause button
        const pauseBtn = toast.querySelector(".nebula-toast-pause");
        const pauseIcon =
            '<svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>';
        const playIcon =
            '<svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>';

        pauseBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            if (paused) {
                // Resume
                paused = false;
                pauseBtn.innerHTML = pauseIcon;
                pauseBtn.title = "Pause";
                startTime = Date.now();
                timerBar.style.transitionDuration = remaining + "ms";
                requestAnimationFrame(() => {
                    timerBar.style.width = "0%";
                });
                timer = setTimeout(dismiss, remaining);
            } else {
                // Pause
                paused = true;
                pauseBtn.innerHTML = playIcon;
                pauseBtn.title = "Resume";
                remaining -= Date.now() - startTime;
                clearTimeout(timer);
                timerBar.style.transitionDuration = "0ms";
                timerBar.style.width = (remaining / TOAST_DURATION) * 100 + "%";
            }
        });

        // Close button
        toast
            .querySelector(".nebula-toast-close")
            .addEventListener("click", (e) => {
                e.stopPropagation();
                clearTimeout(timer);
                dismiss();
            });
    };

    const detectType = (classNames) => {
        if (classNames.indexOf("success") !== -1) return "success";
        if (classNames.indexOf("error") !== -1) return "error";
        if (classNames.indexOf("warning") !== -1) return "warning";
        return "notice";
    };

    const processMessages = () => {
        const wrappers = document.querySelectorAll(
            ".nebula-messages .messages, .page.messages .messages, .messages",
        );
        wrappers.forEach((wrapper) => {
            const msgs = wrapper.querySelectorAll(".message");
            msgs.forEach((msg) => {
                const text = msg.textContent.trim();
                if (!text) return;
                const type = detectType(msg.className);
                createToast(type, text);
            });
            // Hide original messages
            if (wrapper.closest(".nebula-messages")) {
                wrapper.closest(".nebula-messages").style.display = "none";
            } else {
                wrapper.style.display = "none";
            }
        });
    };

    // Process on DOM ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", processMessages);
    } else {
        processMessages();
    }

    // Also observe for dynamically added messages (AJAX)
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (
                    node.nodeType === 1 &&
                    (node.classList.contains("messages") ||
                        node.querySelector(".messages"))
                ) {
                    setTimeout(processMessages, 50);
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // Expose globally for manual use
    window.nebulaToast = (type, message) => {
        createToast(type, message);
    };
})();
