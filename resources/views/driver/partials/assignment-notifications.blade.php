<div id="driverAssignmentAlerts" class="driver-assignment-alerts" aria-live="polite" aria-atomic="false"></div>
<audio id="driverAssignmentSound" preload="auto" src="{{ asset('assets/sounds/assignment-alert.wav') }}"></audio>
<button type="button" id="driverTestAssignmentSound" class="driver-test-sound-btn" title="{{ __('driver_portal.notifications.test_sound') }}">
    <i class="fa fa-volume-up"></i> {{ __('driver_portal.notifications.test_sound') }}
</button>

@php
    $assignmentNotificationLabels = [
        'title' => __('driver_portal.notifications.new_assignment_title'),
        'body' => __('driver_portal.notifications.new_assignment_body'),
        'view' => __('driver_portal.notifications.view_order'),
        'dismiss' => __('driver_portal.notifications.dismiss'),
        'sound_enabled' => __('driver_portal.notifications.sound_enabled'),
        'test_sound' => __('driver_portal.notifications.test_sound'),
    ];
@endphp

<style>
    .driver-assignment-alerts {
        position: fixed;
        top: 4.5rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1080;
        width: min(520px, calc(100vw - 1.5rem));
        display: flex;
        flex-direction: column;
        gap: .65rem;
        pointer-events: none;
    }
    .driver-assignment-alert {
        pointer-events: auto;
        border: 1px solid rgba(255, 255, 255, .35);
        border-radius: .9rem;
        background: linear-gradient(120deg, #023e7d 0%, #0496a8 100%);
        color: #fff;
        box-shadow: 0 10px 28px rgba(2, 62, 125, .28);
        padding: .85rem 1rem;
        animation: driverAlertIn .35s ease both;
    }
    @keyframes driverAlertIn {
        from { opacity: 0; transform: translateY(-12px); }
        to { opacity: 1; transform: none; }
    }
    .driver-assignment-alert__title {
        font-family: 'Bricolage Grotesque', system-ui, sans-serif;
        font-weight: 800;
        font-size: .95rem;
        margin-bottom: .15rem;
    }
    .driver-assignment-alert__body {
        font-size: .9rem;
        opacity: .96;
        margin-bottom: .55rem;
    }
    .driver-assignment-alert__actions {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
    }
    .driver-assignment-alert__actions .btn {
        font-size: .82rem;
        padding: .35rem .75rem;
    }
    .driver-test-sound-btn {
        position: fixed;
        right: .75rem;
        bottom: .75rem;
        z-index: 1075;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: #fff;
        color: var(--deep);
        font-size: .78rem;
        font-weight: 700;
        padding: .45rem .8rem;
        box-shadow: 0 4px 14px rgba(7, 32, 59, .12);
    }
</style>

<script>
    (function () {
        var pollUrl = @json(route('driver.notifications.assignments'));
        var storageKey = 'driverAssignmentKnownIds';
        var pollMs = 20000;
        var knownIds = new Set();
        var initialized = false;
        var audioUnlocked = false;
        var labels = @json($assignmentNotificationLabels);
        var audioEl = document.getElementById('driverAssignmentSound');
        var audioCtx = null;

        try {
            knownIds = new Set(JSON.parse(sessionStorage.getItem(storageKey) || '[]'));
        } catch (error) {
            knownIds = new Set();
        }

        function saveKnownIds() {
            sessionStorage.setItem(storageKey, JSON.stringify(Array.from(knownIds)));
        }

        function getAudioContext() {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                return null;
            }
            if (!audioCtx) {
                audioCtx = new AudioContext();
            }
            return audioCtx;
        }

        function resumeAudioContext() {
            var ctx = getAudioContext();
            if (ctx && ctx.state === 'suspended') {
                return ctx.resume();
            }
            return Promise.resolve();
        }

        function playWebAudioFallback() {
            var ctx = getAudioContext();
            if (!ctx) {
                return Promise.resolve();
            }

            return resumeAudioContext().then(function () {
                [880, 1175, 880].forEach(function (freq, index) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    var start = ctx.currentTime + (index * 0.16);
                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.28);
                    osc.start(start);
                    osc.stop(start + 0.3);
                });
            });
        }

        function playAssignmentSound() {
            if (!audioUnlocked) {
                return Promise.resolve();
            }

            return resumeAudioContext().then(function () {
                if (audioEl) {
                    audioEl.currentTime = 0;
                    var playPromise = audioEl.play();
                    if (playPromise && typeof playPromise.catch === 'function') {
                        return playPromise.catch(function () {
                            return playWebAudioFallback();
                        });
                    }
                    return playPromise;
                }

                return playWebAudioFallback();
            }).catch(function () {
                return playWebAudioFallback();
            });
        }

        function unlockAudio(showReadyMessage) {
            audioUnlocked = true;

            return playAssignmentSound().then(function () {
                if (showReadyMessage) {
                    showInfoAlert(labels.sound_enabled);
                }
            }).catch(function () {
                if (showReadyMessage) {
                    showInfoAlert(labels.sound_enabled);
                }
            });
        }

        function formatBody(order) {
            return labels.body
                .replace(':label', order.label)
                .replace(':status', order.status_label || order.status);
        }

        function showInfoAlert(message) {
            var container = document.getElementById('driverAssignmentAlerts');
            if (!container) {
                return;
            }

            var alert = document.createElement('div');
            alert.className = 'driver-assignment-alert';
            alert.innerHTML =
                '<div class="driver-assignment-alert__body mb-0">' + message + '</div>' +
                '<div class="driver-assignment-alert__actions mt-2">' +
                    '<button type="button" class="btn btn-outline-light btn-sm" data-dismiss="1">' + labels.dismiss + '</button>' +
                '</div>';

            alert.querySelector('[data-dismiss]').addEventListener('click', function () {
                alert.remove();
            });

            container.prepend(alert);
            window.setTimeout(function () {
                if (alert.isConnected) {
                    alert.remove();
                }
            }, 3500);
        }

        function showAlert(order) {
            var container = document.getElementById('driverAssignmentAlerts');
            if (!container) {
                return;
            }

            var alert = document.createElement('div');
            alert.className = 'driver-assignment-alert';
            alert.innerHTML =
                '<div class="driver-assignment-alert__title"><i class="fa fa-bell me-1"></i>' + labels.title + '</div>' +
                '<div class="driver-assignment-alert__body">' + formatBody(order) + '</div>' +
                '<div class="driver-assignment-alert__actions">' +
                    '<a href="' + order.url + '" class="btn btn-light btn-sm">' + labels.view + '</a>' +
                    '<button type="button" class="btn btn-outline-light btn-sm" data-dismiss="1">' + labels.dismiss + '</button>' +
                '</div>';

            alert.querySelector('[data-dismiss]').addEventListener('click', function () {
                alert.remove();
            });

            container.prepend(alert);
        }

        function pollAssignments() {
            fetch(pollUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('poll failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    var orders = data.orders || [];
                    var newOrders = [];

                    orders.forEach(function (order) {
                        if (!knownIds.has(order.id)) {
                            if (initialized) {
                                newOrders.push(order);
                            }
                            knownIds.add(order.id);
                        }
                    });

                    if (newOrders.length) {
                        playAssignmentSound();
                        newOrders.forEach(showAlert);
                    }

                    initialized = true;
                    saveKnownIds();
                })
                .catch(function () {
                    // Silent retry on next interval.
                });
        }

        document.addEventListener('click', function onFirstInteraction() {
            if (!audioUnlocked) {
                unlockAudio(true);
            }
            document.removeEventListener('click', onFirstInteraction);
        }, { once: true });

        document.addEventListener('touchstart', function onFirstTouch() {
            if (!audioUnlocked) {
                unlockAudio(true);
            }
            document.removeEventListener('touchstart', onFirstTouch);
        }, { once: true });

        var testBtn = document.getElementById('driverTestAssignmentSound');
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                unlockAudio(false).then(function () {
                    showAlert({
                        label: '#TEST',
                        status: 'pending',
                        status_label: 'Pending',
                        url: @json(route('driver.orders.index')),
                    });
                });
            });
        }

        window.driverPreviewAssignmentAlert = function () {
            unlockAudio(false).then(function () {
                showAlert({
                    label: '#PREVIEW',
                    status: 'pending',
                    status_label: 'Pending',
                    url: @json(route('driver.orders.index')),
                });
            });
        };

        pollAssignments();
        window.setInterval(pollAssignments, pollMs);
    })();
</script>
