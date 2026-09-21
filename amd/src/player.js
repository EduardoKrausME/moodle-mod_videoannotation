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
 * Common player adapters for HTML5/HLS, YouTube, and Vimeo.
 *
 * @module     mod_videoannotation/player
 * @package   mod_videoannotation
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function () {
    const loadScript = (url, ready) => new Promise((resolve, reject) => {
        if (ready()) {
            resolve();
            return;
        }
        const existing = document.querySelector('script[data-videoannotation-src="' + url + '"]');
        if (existing) {
            existing.addEventListener('load', () => resolve(), {once: true});
            existing.addEventListener('error', reject, {once: true});
            return;
        }
        const script = document.createElement('script');
        script.src = url;
        script.dataset.videoannotationSrc = url;
        script.onload = () => resolve();
        script.onerror = reject;
        document.head.appendChild(script);
    });

    class Emitter {
        constructor() {
            this.handlers = {};
        }

        on(name, handler) {
            this.handlers[name] = this.handlers[name] || [];
            this.handlers[name].push(handler);
        }

        emit(name, ...args) {
            (this.handlers[name] || []).forEach((handler) => handler(...args));
        }

        onPlay(handler) {
            this.on('play', handler);
        }

        onPause(handler) {
            this.on('pause', handler);
        }

        onTimeUpdate(handler) {
            this.on('timeupdate', handler);
        }

        onSeek(handler) {
            this.on('seek', handler);
        }

        onEnded(handler) {
            this.on('ended', handler);
        }

        onRateChange(handler) {
            this.on('ratechange', handler);
        }
    }

    class Html5Adapter extends Emitter {
        constructor(root, config) {
            super();
            this.root = root;
            this.config = config;
            this.video = root.querySelector('[data-region="html5-player"]');
            this.lastTime = 0;
        }

        initialise() {
            if (!this.video) {
                return Promise.reject(new Error('HTML5 player element is missing'));
            }
            if (this.config.disabledownload) {
                this.video.setAttribute('controlsList', 'nodownload');
            }
            if (this.config.disablepip) {
                this.video.disablePictureInPicture = true;
            }
            if (this.config.disablecontextmenu) {
                this.video.addEventListener('contextmenu', (event) => event.preventDefault());
            }
            const prepare = this.config.hls ? this.prepareHls() : Promise.resolve();
            return prepare.then(() => {
                this.video.addEventListener('play', () => this.emit('play'));
                this.video.addEventListener('pause', () => this.emit('pause'));
                this.video.addEventListener('ended', () => this.emit('ended'));
                this.video.addEventListener('timeupdate', () => {
                    const current = this.getCurrentTime();
                    this.emit('timeupdate', current);
                    this.lastTime = current;
                });
                this.video.addEventListener('seeking', () => this.emit('seek', this.getCurrentTime(), this.lastTime));
                this.video.addEventListener('ratechange', () => {
                    const maximum = Number(this.config.maxplaybackrate || 0);
                    if (maximum > 0 && this.video.playbackRate > maximum) {
                        this.video.playbackRate = maximum;
                    }
                    this.emit('ratechange', this.getPlaybackRate());
                });
                return new Promise((resolve) => {
                    if (this.video.readyState >= 1) {
                        resolve(this);
                    } else {
                        this.video.addEventListener('loadedmetadata', () => resolve(this), {once: true});
                    }
                });
            });
        }

        prepareHls() {
            if (this.video.canPlayType('application/vnd.apple.mpegurl')) {
                this.video.src = this.config.url;
                return Promise.resolve();
            }
            return loadScript('https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js', () => Boolean(window.Hls)).then(() => {
                if (!window.Hls || !window.Hls.isSupported()) {
                    throw new Error('HLS is not supported by this browser');
                }
                this.hls = new window.Hls({enableWorker: true});
                this.hls.loadSource(this.config.url);
                this.hls.attachMedia(this.video);
            });
        }

        play() {
            return this.video.play();
        }

        pause() {
            this.video.pause();
        }

        getCurrentTime() {
            return Number(this.video.currentTime || 0);
        }

        getDuration() {
            return Number.isFinite(this.video.duration) ? Number(this.video.duration) : 0;
        }

        getPlaybackRate() {
            return Number(this.video.playbackRate || 1);
        }

        seek(position) {
            const duration = this.getDuration();
            this.video.currentTime = Math.max(0, duration > 0 ? Math.min(duration, Number(position)) : Number(position));
        }
    }

    let youtubePromise;
    const loadYoutube = () => {
        if (window.YT && window.YT.Player) {
            return Promise.resolve(window.YT);
        }
        if (youtubePromise) {
            return youtubePromise;
        }
        youtubePromise = new Promise((resolve, reject) => {
            const previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = () => {
                if (typeof previous === 'function') {
                    previous();
                }
                resolve(window.YT);
            };
            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.dataset.videoannotationSrc = script.src;
            script.onerror = reject;
            document.head.appendChild(script);
        });
        return youtubePromise;
    };

    class YoutubeAdapter extends Emitter {
        constructor(root, config) {
            super();
            this.root = root;
            this.config = config;
            this.lastTime = 0;
            this.playing = false;
        }

        initialise() {
            return loadYoutube().then((YT) => new Promise((resolve) => {
                this.player = new YT.Player('videoannotation-youtube-player', {
                    videoId: this.config.youtubeid,
                    playerVars: {playsinline: 1, rel: 0, modestbranding: 1},
                    events: {
                        onReady: () => {
                            this.startTimer();
                            resolve(this);
                        },
                        onStateChange: (event) => this.stateChanged(event.data, YT),
                        onPlaybackRateChange: () => this.rateChanged(),
                    },
                });
            }));
        }

        stateChanged(state, YT) {
            if (state === YT.PlayerState.PLAYING) {
                this.playing = true;
                this.emit('play');
            } else if (state === YT.PlayerState.PAUSED) {
                this.playing = false;
                this.emit('pause');
            } else if (state === YT.PlayerState.ENDED) {
                this.playing = false;
                this.emit('ended');
            }
        }

        startTimer() {
            this.timer = window.setInterval(() => {
                const current = this.getCurrentTime();
                if (this.playing && Math.abs(current - this.lastTime) > Math.max(3, this.getPlaybackRate() * 3)) {
                    this.emit('seek', current, this.lastTime);
                }
                if (this.playing) {
                    this.emit('timeupdate', current);
                }
                this.lastTime = current;
            }, 500);
        }

        rateChanged() {
            const maximum = Number(this.config.maxplaybackrate || 0);
            if (maximum > 0 && this.getPlaybackRate() > maximum) {
                const available = this.player.getAvailablePlaybackRates().filter((rate) => rate <= maximum);
                this.player.setPlaybackRate(available.pop() || 1);
            }
            this.emit('ratechange', this.getPlaybackRate());
        }

        play() {
            this.player.playVideo();
            return Promise.resolve();
        }

        pause() {
            this.player.pauseVideo();
        }

        getCurrentTime() {
            return Number(this.player.getCurrentTime() || 0);
        }

        getDuration() {
            return Number(this.player.getDuration() || 0);
        }

        getPlaybackRate() {
            return Number(this.player.getPlaybackRate() || 1);
        }

        seek(position) {
            this.player.seekTo(Math.max(0, Number(position)), true);
        }
    }

    class VimeoAdapter extends Emitter {
        constructor(root, config) {
            super();
            this.root = root;
            this.config = config;
            this.current = 0;
            this.duration = 0;
            this.rate = 1;
        }

        initialise() {
            return loadScript('https://player.vimeo.com/api/player.js', () => Boolean(window.Vimeo && window.Vimeo.Player)).then(() => {
                const element = this.root.querySelector('[data-region="vimeo-player"]');
                const url = 'https://vimeo.com/' + this.config.vimeoid +
                    (this.config.vimeohash ? '/' + this.config.vimeohash : '');
                this.player = new window.Vimeo.Player(element, {url: url, responsive: true});
                this.player.on('play', () => this.emit('play'));
                this.player.on('pause', () => this.emit('pause'));
                this.player.on('ended', () => this.emit('ended'));
                this.player.on('timeupdate', (event) => {
                    this.current = Number(event.seconds || 0);
                    this.duration = Number(event.duration || this.duration || 0);
                    this.emit('timeupdate', this.current);
                });
                this.player.on('seeked', (event) => {
                    const previous = this.current;
                    this.current = Number(event.seconds || 0);
                    this.emit('seek', this.current, previous);
                });
                this.player.on('playbackratechange', (event) => {
                    this.rate = Number(event.playbackRate || 1);
                    const maximum = Number(this.config.maxplaybackrate || 0);
                    if (maximum > 0 && this.rate > maximum) {
                        this.player.setPlaybackRate(maximum).catch(() => {
                        });
                        this.rate = maximum;
                    }
                    this.emit('ratechange', this.rate);
                });
                return this.player.ready().then(() => Promise.all([
                    this.player.getDuration().then((value) => {
                        this.duration = Number(value || 0);
                    }),
                    this.player.getCurrentTime().then((value) => {
                        this.current = Number(value || 0);
                    }),
                    this.player.getPlaybackRate().then((value) => {
                        this.rate = Number(value || 1);
                    }),
                ])).then(() => this);
            });
        }

        play() {
            return this.player.play();
        }

        pause() {
            this.player.pause();
        }

        getCurrentTime() {
            return this.current;
        }

        getDuration() {
            return this.duration;
        }

        getPlaybackRate() {
            return this.rate;
        }

        seek(position) {
            const target = Math.max(0, this.duration > 0 ? Math.min(this.duration, Number(position)) : Number(position));
            this.current = target;
            this.player.setCurrentTime(target).catch(() => {
            });
        }
    }

    const create = (root, config) => {
        let adapter;
        if (config.source === 'youtube') {
            adapter = new YoutubeAdapter(root, config);
        } else if (config.source === 'vimeo') {
            adapter = new VimeoAdapter(root, config);
        } else {
            adapter = new Html5Adapter(root, config);
        }
        return adapter.initialise();
    };

    return {create: create};
});
