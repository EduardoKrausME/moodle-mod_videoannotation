// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tracking and annotation UI controller.
 *
 * @module     mod_videoannotation/main
 * @package   mod_videoannotation
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification', 'mod_videoannotation/player'],
    function (Ajax, Str, Notification, Player) {

        class Activity {
            constructor(root, config) {
                this.root = root;
                this.config = config;
                this.sequence = 0;
                this.sessionkey = this.randomKey();
                this.playing = false;
                this.pendingStart = null;
                this.pendingEnd = null;
                this.lastSample = Number(config.lastposition || 0);
                this.segments = Array.isArray(config.segments) ? config.segments : [];
                this.queue = [];
                this.sending = false;
                this.activeInterval = null;
            }

            initialise() {
                return Player.create(this.root, this.config.player).then((player) => {
                    this.player = player;
                    this.bindTracking();
                    this.bindAnnotations();
                    this.applyResume();
                    this.flushTimer = window.setInterval(() => {
                        if (this.playing) {
                            this.flush();
                        }
                    }, 2000);
                    return this;
                }).catch((error) => Notification.exception(error));
            }

            bindTracking() {
                this.player.onPlay(() => {
                    this.playing = true;
                    const current = this.player.getCurrentTime();
                    this.pendingStart = current;
                    this.pendingEnd = current;
                    this.lastSample = current;
                });
                this.player.onTimeUpdate((current) => {
                    if (!this.playing) {
                        return;
                    }
                    const value = Number(current || 0);
                    const threshold = Math.max(4, this.player.getPlaybackRate() * 3.5);
                    if (Math.abs(value - this.lastSample) > threshold) {
                        this.flush();
                        this.pendingStart = value;
                    }
                    if (this.pendingStart === null) {
                        this.pendingStart = value;
                    }
                    this.pendingEnd = value;
                    this.lastSample = value;
                });
                this.player.onSeek((current, previous) => {
                    if (this.pendingStart !== null && Number(previous) >= this.pendingStart) {
                        this.pendingEnd = Number(previous);
                        this.flush();
                    }
                    this.pendingStart = Number(current);
                    this.pendingEnd = Number(current);
                    this.lastSample = Number(current);
                    if (!this.config.allowseek && !this.isWatched(Number(current))) {
                        this.flush();
                    }
                });
                this.player.onPause(() => {
                    this.playing = false;
                    this.flush();
                });
                this.player.onEnded(() => {
                    this.playing = false;
                    this.flush();
                });
            }

            flush() {
                if (!this.player) {
                    return;
                }
                const current = Number(this.player.getCurrentTime() || 0);
                const duration = Number(this.player.getDuration() || 0);
                if (duration <= 0) {
                    return;
                }
                const start = this.pendingStart === null ? current : Number(this.pendingStart);
                const end = this.pendingEnd === null ? start : Math.max(start, Number(this.pendingEnd));
                this.pendingStart = this.playing ? current : null;
                this.pendingEnd = this.pendingStart;
                this.queue.push({
                    cmid: Number(this.config.cmid),
                    currentposition: current,
                    duration: duration,
                    playbackrate: Number(this.player.getPlaybackRate() || 1),
                    segmentstart: start,
                    segmentend: end,
                    sequence: ++this.sequence,
                    sessionkey: this.sessionkey,
                });
                this.drainQueue();
            }

            drainQueue() {
                if (this.sending || !this.queue.length) {
                    return;
                }
                const pending = this.queue[0];
                this.sending = true;
                Ajax.call([{
                    methodname: 'mod_videoannotation_update_progress',
                    args: pending,
                }])[0].then((response) => {
                    this.queue.shift();
                    this.applyProgress(response);
                    this.sending = false;
                    this.drainQueue();
                }).catch(() => {
                    this.sending = false;
                    this.showMessage('pendingupdates');
                });
            }

            applyProgress(response) {
                try {
                    this.segments = JSON.parse(response.segments || '[]');
                } catch (error) {
                    this.segments = [];
                }
                if (response.reason === 'seekblocked') {
                    this.player.seek(Number(response.lastposition || 0));
                    this.showMessage('seekblocked');
                }
                const percent = Number(response.percent || 0);
                const rounded = Math.round(percent);
                const text = this.root.querySelector('[data-region="percent"]');
                const bar = this.root.querySelector('[data-region="progress-bar"]');
                if (text) {
                    text.textContent = rounded + '%';
                }
                if (bar) {
                    bar.style.width = percent + '%';
                    if (bar.parentElement) {
                        bar.parentElement.setAttribute('aria-valuenow', String(rounded));
                    }
                }
            }

            bindAnnotations() {
                this.root.querySelectorAll('[data-action="seek"]').forEach((button) => {
                    button.addEventListener('click', () => this.player.seek(Number(button.dataset.time || 0)));
                });
                const add = this.root.querySelector('[data-action="add-annotation"]');
                if (add) {
                    add.addEventListener('click', () => {
                        this.player.pause();
                        this.openEditor(this.player.getCurrentTime(), -1, 0, 0);
                    });
                }
                const interval = this.root.querySelector('[data-action="interval"]');
                if (interval) {
                    interval.addEventListener('click', () => this.toggleInterval(interval, 0, 0));
                }
                this.root.querySelectorAll('[data-action="prompt-point"]').forEach((button) => {
                    button.addEventListener('click', () => {
                        this.player.pause();
                        this.openEditor(
                            this.player.getCurrentTime(),
                            -1,
                            Number(button.dataset.promptId || 0),
                            Number(button.dataset.categoryId || 0)
                        );
                    });
                });
                this.root.querySelectorAll('[data-action="prompt-interval"]').forEach((button) => {
                    button.addEventListener('click', () => this.toggleInterval(
                        button,
                        Number(button.dataset.promptId || 0),
                        Number(button.dataset.categoryId || 0)
                    ));
                });
                const save = this.root.querySelector('[data-action="save-annotation"]');
                if (save) {
                    save.addEventListener('click', () => this.saveAnnotation());
                }
                const cancel = this.root.querySelector('[data-action="cancel-editor"]');
                if (cancel) {
                    cancel.addEventListener('click', () => this.closeEditor());
                }
                this.root.querySelectorAll('[data-action="edit-annotation"]').forEach((button) => {
                    button.addEventListener('click', () => this.editAnnotation(button));
                });
                this.root.querySelectorAll('[data-action="delete-annotation"]').forEach((button) => {
                    button.addEventListener('click', () => this.deleteAnnotation(Number(button.dataset.id || 0)));
                });
            }

            toggleInterval(button, promptid, categoryid) {
                if (this.activeInterval && this.activeInterval.button === button) {
                    const end = Number(this.player.getCurrentTime() || 0);
                    const start = this.activeInterval.start;
                    const savedPrompt = this.activeInterval.promptid;
                    const savedCategory = this.activeInterval.categoryid;
                    this.restoreIntervalButton();
                    if (end <= start + 0.05) {
                        Str.get_string('annotationinvalidrange', 'videoannotation').then((message) =>
                            Notification.alert('', message));
                        return;
                    }
                    this.player.pause();
                    this.openEditor(start, end, savedPrompt, savedCategory);
                    return;
                }
                this.restoreIntervalButton();
                this.activeInterval = {
                    button: button,
                    start: Number(this.player.getCurrentTime() || 0),
                    promptid: promptid,
                    categoryid: categoryid,
                    label: button.textContent,
                };
                Str.get_string('finishinterval', 'videoannotation').then((label) => {
                    if (this.activeInterval && this.activeInterval.button === button) {
                        button.textContent = label;
                        button.classList.add('btn-warning');
                    }
                });
            }

            restoreIntervalButton() {
                if (!this.activeInterval) {
                    return;
                }
                this.activeInterval.button.textContent = this.activeInterval.label;
                this.activeInterval.button.classList.remove('btn-warning');
                this.activeInterval = null;
            }

            openEditor(start, end, promptid, categoryid) {
                const editor = this.root.querySelector('[data-region="annotation-editor"]');
                if (!editor) {
                    return;
                }
                editor.querySelector('[data-field="annotationid"]').value = '0';
                editor.querySelector('[data-field="promptid"]').value = String(promptid || 0);
                editor.querySelector('[data-field="starttime"]').value = String(Number(start || 0));
                editor.querySelector('[data-field="endtime"]').value = String(Number(end));
                editor.querySelector('[data-field="content"]').value = '';
                const category = editor.querySelector('[data-field="categoryid"]');
                if (category && categoryid) {
                    category.value = String(categoryid);
                } else if (category) {
                    category.value = '0';
                }
                const time = editor.querySelector('[data-region="editor-time"]');
                if (time) {
                    time.textContent = end >= 0
                        ? this.formatTime(start) + '–' + this.formatTime(end)
                        : this.formatTime(start);
                }
                editor.classList.remove('d-none');
                editor.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                editor.querySelector('[data-field="content"]').focus();
            }

            closeEditor() {
                const editor = this.root.querySelector('[data-region="annotation-editor"]');
                if (editor) {
                    editor.classList.add('d-none');
                }
            }

            editAnnotation(button) {
                const editor = this.root.querySelector('[data-region="annotation-editor"]');
                if (!editor) {
                    return;
                }
                this.player.pause();
                const start = Number(button.dataset.start || 0);
                const end = Number(button.dataset.end || -1);
                editor.querySelector('[data-field="annotationid"]').value = String(Number(button.dataset.id || 0));
                editor.querySelector('[data-field="promptid"]').value = String(Number(button.dataset.promptId || 0));
                editor.querySelector('[data-field="starttime"]').value = String(start);
                editor.querySelector('[data-field="endtime"]').value = String(end);
                editor.querySelector('[data-field="categoryid"]').value = String(Number(button.dataset.categoryId || 0));
                editor.querySelector('[data-field="content"]').value = button.dataset.content || '';
                const time = editor.querySelector('[data-region="editor-time"]');
                if (time) {
                    time.textContent = end >= 0 ? this.formatTime(start) + '–' + this.formatTime(end) : this.formatTime(start);
                }
                editor.classList.remove('d-none');
                editor.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                editor.querySelector('[data-field="content"]').focus();
            }

            saveAnnotation() {
                const editor = this.root.querySelector('[data-region="annotation-editor"]');
                if (!editor) {
                    return;
                }
                const content = editor.querySelector('[data-field="content"]').value.trim();
                if (!content) {
                    Str.get_string('annotationrequired', 'videoannotation').then((message) =>
                        Notification.alert('', message));
                    return;
                }
                const args = {
                    cmid: Number(this.config.cmid),
                    annotationid: Number(editor.querySelector('[data-field="annotationid"]').value || 0),
                    promptid: Number(editor.querySelector('[data-field="promptid"]').value || 0),
                    categoryid: Number(editor.querySelector('[data-field="categoryid"]').value || 0),
                    starttime: Number(editor.querySelector('[data-field="starttime"]').value || 0),
                    endtime: Number(editor.querySelector('[data-field="endtime"]').value || -1),
                    content: content,
                };
                Ajax.call([{
                    methodname: 'mod_videoannotation_save_annotation',
                    args: args,
                }])[0].then(() => window.location.reload()).catch((error) => Notification.exception(error));
            }

            deleteAnnotation(id) {
                if (!id) {
                    return;
                }
                Promise.all([
                    Str.get_string('confirmdeleteannotation', 'videoannotation'),
                    Str.get_string('yes'),
                    Str.get_string('no'),
                ]).then((strings) => {
                    Notification.confirm('', strings[0], strings[1], strings[2], () => {
                        Ajax.call([{
                            methodname: 'mod_videoannotation_delete_annotation',
                            args: {cmid: Number(this.config.cmid), annotationid: id},
                        }])[0].then(() => window.location.reload()).catch((error) => Notification.exception(error));
                    });
                });
            }

            applyResume() {
                const position = Number(this.config.lastposition || 0);
                if (position <= 1 || Number(this.config.resumeplayback) === 0) {
                    return;
                }
                if (Number(this.config.resumeplayback) === 1) {
                    this.player.seek(position);
                    return;
                }
                Promise.all([
                    Str.get_string('resumequestion', 'videoannotation', this.formatTime(position)),
                    Str.get_string('resumeyes', 'videoannotation'),
                    Str.get_string('resumeno', 'videoannotation'),
                ]).then((strings) => Notification.confirm('', strings[0], strings[1], strings[2],
                    () => this.player.seek(position), () => this.player.seek(0)));
            }

            isWatched(position) {
                return this.segments.some((segment) =>
                    position >= Number(segment[0]) - 0.5 && position <= Number(segment[1]) + 0.5);
            }

            showMessage(key) {
                Str.get_string(key, 'videoannotation').then((message) => {
                    const element = this.root.querySelector('[data-region="tracking-message"]');
                    if (!element) {
                        return;
                    }
                    element.textContent = message;
                    element.classList.remove('d-none');
                    window.setTimeout(() => element.classList.add('d-none'), 5000);
                });
            }

            randomKey() {
                const bytes = new Uint8Array(24);
                window.crypto.getRandomValues(bytes);
                return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
            }

            formatTime(seconds) {
                const value = Math.max(0, Math.round(Number(seconds || 0)));
                const hours = Math.floor(value / 3600);
                const minutes = Math.floor((value % 3600) / 60);
                const remaining = value % 60;
                return (hours ? String(hours).padStart(2, '0') + ':' : '') +
                    String(minutes).padStart(2, '0') + ':' + String(remaining).padStart(2, '0');
            }
        }

        const init = () => {
            document.querySelectorAll('[data-region="videoannotation"]').forEach((root) => {
                try {
                    const config = JSON.parse(root.dataset.config || '{}');
                    new Activity(root, config).initialise();
                } catch (error) {
                    Notification.exception(error);
                }
            });
        };

        return {init: init};
    });
