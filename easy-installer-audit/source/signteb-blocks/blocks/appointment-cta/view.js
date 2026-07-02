/**
 * SignTeb Blocks — Appointment CTA: Frontend AJAX
 * Vanilla JS — no jQuery
 */

( function () {
	'use strict';

	const FA_MONTHS = [ 'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند' ];
	const FA_DIGITS = [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ];

	function toFa( n ) {
		return String( n ).replace( /[0-9]/g, d => FA_DIGITS[ d ] );
	}

	// تقویم در این پروژه از تاریخ میلادی استفاده می‌کند (سازگار با DB)
	// اما نمایش به کاربر را خوانا می‌کنیم با نام ماه فارسی به همراه عدد میلادی

	document.querySelectorAll( '[data-block="signteb/appointment-cta"]' ).forEach( initBlock );

	function initBlock( section ) {
		initCalendar( section );
		initForm( section );
	}

	// ── Calendar Widget ─────────────────────────────────────────────────────────

	function initCalendar( section ) {
		const calEl = section.querySelector( '.stmb-calendar' );
		if ( ! calEl ) return; // بدون پزشک انتخابی → calendar نداریم

		const doctorId   = calEl.dataset.doctor;
		const daysWrap   = calEl.querySelector( '.stmb-calendar__days' );
		const monthLabel = calEl.querySelector( '.stmb-cal-month-label' );
		const loading    = calEl.querySelector( '.stmb-calendar__loading' );
		const prevBtn    = calEl.querySelector( '[data-dir="-1"]' );
		const nextBtn    = calEl.querySelector( '[data-dir="1"]' );

		const slotsWrap   = section.querySelector( '.stmb-slots-wrap' );
		const slotsGrid   = section.querySelector( '.stmb-slots-grid' );
		const noSlotsMsg  = section.querySelector( '.stmb-no-slots' );
		const dateLabel   = section.querySelector( '.stmb-selected-date-label' );

		const dateInput   = section.querySelector( '.stmb-appt-date-input' );
		const timeInput   = section.querySelector( '.stmb-appt-time-input' );
		const summaryBox  = section.querySelector( '.stmb-selected-summary' );
		const summaryText = section.querySelector( '.stmb-summary-text' );
		const changeBtn   = section.querySelector( '.stmb-change-time' );

		const formWrap    = section.querySelector( '.stmb-appt-form-wrap' );
		const step2Label  = section.querySelector( '.stmb-appt-step-2' );
		const datetimeStep= section.querySelector( '.stmb-appt-datetime-step' );

		const ajaxUrl = ( section.querySelector( '.stmb-appt-submit' )?.dataset.ajax ) || '/wp-admin/admin-ajax.php';
		const nonce   = section.querySelector( '[name="stmc_appointment_nonce"]' )?.value || '';

		const today    = new Date();
		let viewYear   = today.getFullYear();
		let viewMonth  = today.getMonth(); // 0-indexed
		let selectedDate = null;

		const minMonth = today.getFullYear() * 12 + today.getMonth();

		renderMonth();

		prevBtn?.addEventListener( 'click', () => { shiftMonth( -1 ); } );
		nextBtn?.addEventListener( 'click', () => { shiftMonth( 1 ); } );

		changeBtn?.addEventListener( 'click', function () {
			summaryBox.hidden  = true;
			datetimeStep.hidden = false;
			formWrap.hidden     = true;
			step2Label.hidden   = true;
			dateInput.value = '';
			timeInput.value = '';
		} );

		function shiftMonth( dir ) {
			viewMonth += dir;
			if ( viewMonth > 11 ) { viewMonth = 0; viewYear++; }
			if ( viewMonth < 0 )  { viewMonth = 11; viewYear--; }
			renderMonth();
		}

		function pad( n ) { return String( n ).padStart( 2, '0' ); }

		function renderMonth() {
			monthLabel.textContent = FA_MONTHS_GREGORIAN( viewMonth ) + ' ' + toFa( viewYear );

			const curMonthIdx = viewYear * 12 + viewMonth;
			prevBtn.disabled = curMonthIdx <= minMonth;

			loading.hidden = false;
			daysWrap.innerHTML = '';

			fetchAvailableDays( viewYear, viewMonth ).then( function ( days ) {
				loading.hidden = true;
				buildDaysGrid( days );
			} );
		}

		function FA_MONTHS_GREGORIAN( monthIdx ) {
			// نمایش نام ماه میلادی به فارسی برای راحتی خوانش (ساده‌سازی شده)
			const names = [ 'ژانویه','فوریه','مارس','آپریل','می','ژوئن','جولای','آگوست','سپتامبر','اکتبر','نوامبر','دسامبر' ];
			return names[ monthIdx ];
		}

		function buildDaysGrid( availableDaysMap ) {
			const firstOfMonth = new Date( viewYear, viewMonth, 1 );
			// تبدیل به شروع هفته شنبه=0 ... جمعه=6 برای چیدمان RTL فارسی
			// JS getDay(): یکشنبه=0 ... شنبه=6 → باید بچرخانیم تا شنبه اول ستون باشد
			let startOffset = ( firstOfMonth.getDay() + 1 ) % 7; // شنبه=0

			const daysInMonth = new Date( viewYear, viewMonth + 1, 0 ).getDate();

			for ( let i = 0; i < startOffset; i++ ) {
				const empty = document.createElement( 'div' );
				empty.className = 'stmb-cal-day empty';
				daysWrap.appendChild( empty );
			}

			const todayStr = today.getFullYear() + '-' + pad( today.getMonth() + 1 ) + '-' + pad( today.getDate() );

			for ( let d = 1; d <= daysInMonth; d++ ) {
				const dateStr = viewYear + '-' + pad( viewMonth + 1 ) + '-' + pad( d );
				const cell    = document.createElement( 'div' );
				cell.textContent = toFa( d );
				cell.dataset.date = dateStr;

				const isPast    = dateStr < todayStr;
				const hasSlots  = ! isPast && !! availableDaysMap[ dateStr ];

				cell.className = 'stmb-cal-day ' + ( hasSlots ? 'has-slots' : 'no-slots' );
				if ( dateStr === todayStr ) cell.classList.add( 'is-today' );
				if ( dateStr === selectedDate ) cell.classList.add( 'is-selected' );

				if ( hasSlots ) {
					cell.addEventListener( 'click', function () {
						selectedDate = dateStr;
						daysWrap.querySelectorAll( '.stmb-cal-day' ).forEach( c => c.classList.remove( 'is-selected' ) );
						cell.classList.add( 'is-selected' );
						loadSlots( dateStr );
					} );
				}

				daysWrap.appendChild( cell );
			}
		}

		function fetchAvailableDays( year, month ) {
			const monthStr = year + '-' + pad( month + 1 );
			const data = new URLSearchParams( {
				action:    'stmc_get_available_days',
				nonce:     nonce,
				doctor_id: doctorId,
				month:     monthStr,
			} );

			return fetch( ajaxUrl, { method: 'POST', body: data } )
				.then( r => r.json() )
				.then( json => ( json.success ? json.data.days : {} ) )
				.catch( () => ( {} ) );
		}

		function loadSlots( dateStr ) {
			slotsWrap.hidden  = false;
			slotsGrid.innerHTML = '';
			noSlotsMsg.hidden = true;
			dateLabel.textContent = formatDateFa( dateStr );

			const data = new URLSearchParams( {
				action:    'stmc_get_available_slots',
				nonce:     nonce,
				doctor_id: doctorId,
				date:      dateStr,
			} );

			fetch( ajaxUrl, { method: 'POST', body: data } )
				.then( r => r.json() )
				.then( function ( json ) {
					const slots = json.success ? json.data.slots : [];

					if ( ! slots.length ) {
						noSlotsMsg.hidden = false;
						return;
					}

					slots.forEach( function ( slot ) {
						const btn = document.createElement( 'button' );
						btn.type = 'button';
						btn.className = 'stmb-slot-btn';
						btn.textContent = slot.label;
						btn.dataset.time = slot.time;

						btn.addEventListener( 'click', function () {
							slotsGrid.querySelectorAll( '.stmb-slot-btn' ).forEach( b => b.classList.remove( 'is-selected' ) );
							btn.classList.add( 'is-selected' );
							confirmSelection( dateStr, slot.time, slot.label );
						} );

						slotsGrid.appendChild( btn );
					} );
				} );
		}

		function confirmSelection( dateStr, time, timeLabel ) {
			dateInput.value = dateStr;
			timeInput.value = time;

			summaryText.textContent = formatDateFa( dateStr ) + ' — ساعت ' + timeLabel;
			summaryBox.hidden   = false;
			datetimeStep.hidden = true;

			formWrap.hidden    = false;
			step2Label.hidden  = false;

			// اسکرول نرم به فرم بعدی
			step2Label.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}

		function formatDateFa( dateStr ) {
			const [ y, m, d ] = dateStr.split( '-' ).map( Number );
			const weekdays = [ 'یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه' ];
			const wd = new Date( y, m - 1, d ).getDay();
			return weekdays[ wd ] + ' ' + toFa( d ) + ' ' + FA_MONTHS_GREGORIAN( m - 1 ) + ' ' + toFa( y );
		}
	}

	// ── Form Submission ──────────────────────────────────────────────────────────

	function initForm( section ) {
		const submitBtn  = section.querySelector( '.stmb-appt-submit' );
		const formWrap   = section.querySelector( '.stmb-appt-form-wrap' );
		const successBox = section.querySelector( '.stmb-appt-success' );
		const errorBox   = section.querySelector( '.stmb-appt-error' );

		if ( ! submitBtn ) return;

		submitBtn.addEventListener( 'click', async function () {
			// Clear previous errors
			hideEl( errorBox );

			// Collect all form data
			const data = new FormData();
			formWrap.querySelectorAll( 'input, select, textarea' ).forEach( function ( el ) {
				if ( el.name ) data.append( el.name, el.value );
			} );

			// شامل کردن فیلدهای تاریخ/ساعت که خارج از form-wrap هستند (مرحله ۱)
			const dateInput = section.querySelector( '.stmb-appt-date-input' );
			const timeInput = section.querySelector( '.stmb-appt-time-input' );
			if ( dateInput?.value ) data.append( 'stmc_appt_date', dateInput.value );
			if ( timeInput?.value ) data.append( 'stmc_appt_time', timeInput.value );

			// Validate required fields
			const name  = data.get( 'stmc_name'  )?.trim();
			const phone = data.get( 'stmc_phone' )?.trim();

			if ( ! name || name.length < 2 ) {
				showError( errorBox, 'لطفاً نام کامل خود را وارد کنید.' );
				section.querySelector( '[name="stmc_name"]' )?.focus();
				return;
			}

			if ( ! phone || phone.replace( /[^0-9]/g, '' ).length < 10 ) {
				showError( errorBox, 'شماره تلفن نامعتبر است.' );
				section.querySelector( '[name="stmc_phone"]' )?.focus();
				return;
			}

			// Show loading state
			setLoading( submitBtn, true );

			try {
				const ajaxUrl = submitBtn.dataset.ajax || '/wp-admin/admin-ajax.php';
				const res     = await fetch( ajaxUrl, {
					method: 'POST',
					body:   data,
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
				} );

				const json = await res.json();

				if ( json.success ) {
					// Show success
					hideEl( formWrap );
					hideEl( section.querySelector( '.stmb-appt-step-2' ) );
					hideEl( section.querySelector( '.stmb-selected-summary' ) );
					showEl( successBox );
					successBox.querySelector( '.stmb-appt-success__msg' ).textContent = json.data.message;
					// Optional: smooth scroll to section
					section.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				} else if ( json.data?.slot_taken ) {
					// زمان انتخابی همزمان توسط شخص دیگری رزرو شد — برگرداندن به انتخاب مجدد
					showError( errorBox, json.data.message );
					section.querySelector( '.stmb-change-time' )?.click();
				} else {
					showError( errorBox, json.data?.message || 'خطا در ارسال. لطفاً مجدداً تلاش کنید.' );
				}
			} catch ( err ) {
				showError( errorBox, 'خطای شبکه. لطفاً اتصال اینترنت خود را بررسی کنید.' );
			} finally {
				setLoading( submitBtn, false );
			}
		} );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────────

	function setLoading( btn, isLoading ) {
		const textEl    = btn.querySelector( '.stmb-appt-submit__text' );
		const loadingEl = btn.querySelector( '.stmb-appt-submit__loading' );

		btn.disabled = isLoading;
		if ( textEl )    textEl.hidden    = isLoading;
		if ( loadingEl ) loadingEl.hidden = ! isLoading;
	}

	function showError( el, msg ) {
		if ( ! el ) return;
		el.textContent = msg;
		showEl( el );
		el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	function showEl( el ) { if ( el ) el.hidden = false; }
	function hideEl( el ) { if ( el ) el.hidden = true; }

} )();
