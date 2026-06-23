/**
 * Self-contained Jalali (Solar Hijri) date picker for the booking form.
 *
 * Ports the same 33-year break-point algorithm used by includes/Calendar/JalaliConverter.php
 * so the front-end can render a Jalali calendar grid without a server round trip. Writes the
 * Gregorian Y-m-d equivalent into the existing hidden #nobatyar-date input and fires a native
 * 'change' event on it, so booking-form.js's existing listener wiring keeps working unmodified.
 */
(function () {
    'use strict';

    var MONTH_NAMES = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];
    var WEEKDAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    var BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    function div(a, b) {
        return Math.trunc(a / b);
    }

    function mod(a, b) {
        return a - div(a, b) * b;
    }

    function jalCal(jy) {
        var bl = BREAKS.length;
        var gy = jy + 621;
        var leapJ = -14;
        var jp = BREAKS[0];

        if (jy < jp || jy >= BREAKS[bl - 1]) {
            throw new Error('Invalid Jalaali year ' + jy);
        }

        var jump = 0;
        var i;

        for (i = 1; i < bl; i += 1) {
            var jm = BREAKS[i];
            jump = jm - jp;

            if (jy < jm) {
                break;
            }

            leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
            jp = jm;
        }

        var n = jy - jp;
        leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);

        if (mod(jump, 33) === 4 && (jump - n) === 4) {
            leapJ += 1;
        }

        var leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
        var march = 20 + leapJ - leapG;

        if ((jump - n) < 6) {
            n = n - jump + div(jump + 4, 33) * 33;
        }

        var leap = mod(mod(n + 1, 33) - 1, 4);

        if (leap === -1) {
            leap = 4;
        }

        return { leap: leap, gy: gy, march: march };
    }

    function g2d(gy, gm, gd) {
        var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4)
            + div(153 * mod(gm + 9, 12) + 2, 5)
            + gd - 34840408;

        d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;

        return d;
    }

    function d2g(jdn) {
        var j = 4 * jdn + 139361631;
        j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;

        var i = div(mod(j, 1461), 4) * 5 + 308;
        var gd = div(mod(i, 153), 5) + 1;
        var gm = mod(div(i, 153), 12) + 1;
        var gy = div(j, 1461) - 100100 + div(8 - gm, 6);

        return { year: gy, month: gm, day: gd };
    }

    function j2d(jy, jm, jd) {
        var r = jalCal(jy);

        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    }

    function d2j(jdn) {
        var gy = d2g(jdn).year;
        var jy = gy - 621;
        var r = jalCal(jy);
        var jdn1f = g2d(r.gy, 3, r.march);
        var k = jdn - jdn1f;

        if (k >= 0) {
            if (k <= 185) {
                return { year: jy, month: 1 + div(k, 31), day: mod(k, 31) + 1 };
            }

            k -= 186;
        } else {
            jy -= 1;
            k += 179;

            if (r.leap === 1) {
                k += 1;
            }
        }

        return { year: jy, month: 7 + div(k, 30), day: mod(k, 30) + 1 };
    }

    function toJalali(gy, gm, gd) {
        return d2j(g2d(gy, gm, gd));
    }

    function toGregorian(jy, jm, jd) {
        return d2g(j2d(jy, jm, jd));
    }

    function isLeapJalaliYear(jy) {
        return jalCal(jy).leap === 0;
    }

    function jalaliMonthLength(jy, jm) {
        if (jm <= 6) {
            return 31;
        }

        if (jm <= 11) {
            return 30;
        }

        return isLeapJalaliYear(jy) ? 30 : 29;
    }

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function gregorianString(gy, gm, gd) {
        return gy + '-' + pad(gm) + '-' + pad(gd);
    }

    function toPersianDigits(value) {
        var persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        return String(value).replace(/[0-9]/g, function (digit) {
            return persian[digit];
        });
    }

    function initPicker(displayInput, hiddenInput) {
        var today = new Date();
        var todayJalali = toJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        var todayGregorianStr = gregorianString(today.getFullYear(), today.getMonth() + 1, today.getDate());

        var state = { year: todayJalali.year, month: todayJalali.month };

        var wrapper = document.createElement('div');
        wrapper.className = 'nobatyar-jalali-calendar';
        wrapper.setAttribute('hidden', 'hidden');
        displayInput.insertAdjacentElement('afterend', wrapper);

        function close() {
            wrapper.setAttribute('hidden', 'hidden');
        }

        function render() {
            wrapper.innerHTML = '';

            var header = document.createElement('div');
            header.className = 'nobatyar-jalali-header';

            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'nobatyar-jalali-nav';
            prevBtn.textContent = '‹';
            prevBtn.setAttribute('aria-label', 'ماه قبل');
            prevBtn.addEventListener('click', function () {
                state.month -= 1;
                if (state.month < 1) {
                    state.month = 12;
                    state.year -= 1;
                }
                render();
            });

            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'nobatyar-jalali-nav';
            nextBtn.textContent = '›';
            nextBtn.setAttribute('aria-label', 'ماه بعد');
            nextBtn.addEventListener('click', function () {
                state.month += 1;
                if (state.month > 12) {
                    state.month = 1;
                    state.year += 1;
                }
                render();
            });

            var title = document.createElement('span');
            title.className = 'nobatyar-jalali-title';
            title.textContent = MONTH_NAMES[state.month - 1] + ' ' + toPersianDigits(state.year);

            header.appendChild(nextBtn);
            header.appendChild(title);
            header.appendChild(prevBtn);
            wrapper.appendChild(header);

            var weekRow = document.createElement('div');
            weekRow.className = 'nobatyar-jalali-weekdays';
            WEEKDAY_NAMES.forEach(function (label) {
                var cell = document.createElement('span');
                cell.textContent = label.charAt(0);
                weekRow.appendChild(cell);
            });
            wrapper.appendChild(weekRow);

            var grid = document.createElement('div');
            grid.className = 'nobatyar-jalali-grid';

            var firstOfMonthGregorian = toGregorian(state.year, state.month, 1);
            var firstWeekday = new Date(
                firstOfMonthGregorian.year,
                firstOfMonthGregorian.month - 1,
                firstOfMonthGregorian.day
            ).getDay();
            var leadingBlanks = (firstWeekday + 1) % 7;

            var b;
            for (b = 0; b < leadingBlanks; b += 1) {
                grid.appendChild(document.createElement('span'));
            }

            var daysInMonth = jalaliMonthLength(state.year, state.month);
            var day;

            for (day = 1; day <= daysInMonth; day += 1) {
                var dayGregorian = toGregorian(state.year, state.month, day);
                var dayGregorianStr = gregorianString(dayGregorian.year, dayGregorian.month, dayGregorian.day);

                var cellButton = document.createElement('button');
                cellButton.type = 'button';
                cellButton.className = 'nobatyar-jalali-day';
                cellButton.textContent = toPersianDigits(day);

                if (dayGregorianStr < todayGregorianStr) {
                    cellButton.disabled = true;
                    cellButton.classList.add('is-past');
                } else {
                    cellButton.addEventListener('click', (function (gregorianStr, jy, jm, jd) {
                        return function () {
                            hiddenInput.value = gregorianStr;
                            displayInput.value = toPersianDigits(jy) + '/' + toPersianDigits(pad(jm)) + '/' + toPersianDigits(pad(jd));
                            close();
                            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                        };
                    }(dayGregorianStr, state.year, state.month, day)));
                }

                if (dayGregorianStr === todayGregorianStr) {
                    cellButton.classList.add('is-today');
                }

                if (hiddenInput.value && hiddenInput.value === dayGregorianStr) {
                    cellButton.classList.add('is-selected');
                }

                grid.appendChild(cellButton);
            }

            wrapper.appendChild(grid);
        }

        displayInput.addEventListener('click', function () {
            if (wrapper.hasAttribute('hidden')) {
                render();
                wrapper.removeAttribute('hidden');
            } else {
                close();
            }
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target) && event.target !== displayInput) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                close();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var displayInput = document.getElementById('nobatyar-date-display');
        var hiddenInput = document.getElementById('nobatyar-date');

        if (!displayInput || !hiddenInput) {
            return;
        }

        initPicker(displayInput, hiddenInput);
    });
})();
