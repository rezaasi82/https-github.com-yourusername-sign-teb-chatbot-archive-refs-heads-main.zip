import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { useAsync } from '../hooks/useAsync';

interface WizardProps {
	onComplete: () => void;
}

/**
 * One-click setup.
 *
 * Steps come from the server rather than being duplicated here, so the
 * questions and the fields they write can never drift apart.
 */
export function Wizard( { onComplete }: WizardProps ): JSX.Element {
	const { data, loading } = useAsync( () => api.wizard(), [] );
	const [ step, setStep ] = useState( 0 );
	const [ answers, setAnswers ] = useState< Record< string, string > >( {} );
	const [ analyzeExisting, setAnalyzeExisting ] = useState( true );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( data?.defaults ) {
			setAnswers( data.defaults );
		}
	}, [ data ] );

	if ( loading || ! data ) {
		return <p className="medora-loading">{ __( 'Loading…', 'medora-authority' ) }</p>;
	}

	const steps = data.steps;
	const current = steps[ step ];
	const isLast = step === steps.length - 1;

	if ( ! current ) {
		return <></>;
	}

	const finish = async () => {
		setSubmitting( true );
		setError( '' );

		try {
			const result = await api.completeWizard( {
				...answers,
				analyze_existing: analyzeExisting,
			} );

			if ( result.queued_posts > 0 ) {
				// Reassure rather than leave the dashboard looking empty while
				// the queue drains.
				window.setTimeout( onComplete, 400 );
			} else {
				onComplete();
			}
		} catch ( completionError ) {
			setError( ( completionError as Error ).message );
			setSubmitting( false );
		}
	};

	return (
		<div className="medora-wizard">
			<ol className="medora-wizard__progress">
				{ steps.map( ( item, index ) => (
					<li
						key={ item.id }
						className={ index <= step ? 'is-done' : '' }
						aria-current={ index === step ? 'step' : undefined }
					>
						<span>{ index + 1 }</span>
					</li>
				) ) }
			</ol>

			<div className="medora-wizard__body">
				<h1>{ current.title }</h1>
				<p>{ current.description }</p>

				{ current.field && current.options && (
					<div className="medora-wizard__options">
						{ current.options.map( ( option ) => (
							<label
								key={ option.value }
								className={
									answers[ current.field! ] === option.value
										? 'medora-preset is-active'
										: 'medora-preset'
								}
							>
								<input
									type="radio"
									name={ current.field }
									value={ option.value }
									checked={
										answers[ current.field! ] === option.value
									}
									onChange={ () =>
										setAnswers( ( previous ) => ( {
											...previous,
											[ current.field! ]: option.value,
										} ) )
									}
								/>
								<strong>{ option.label }</strong>
							</label>
						) ) }
					</div>
				) }

				{ current.field && ! current.options && (
					<input
						type="text"
						className="medora-wizard__input"
						value={ answers[ current.field ] ?? '' }
						onChange={ ( event ) =>
							setAnswers( ( previous ) => ( {
								...previous,
								[ current.field! ]: event.target.value,
							} ) )
						}
					/>
				) }

				{ isLast && (
					<label className="medora-checkbox">
						<input
							type="checkbox"
							checked={ analyzeExisting }
							onChange={ ( event ) =>
								setAnalyzeExisting( event.target.checked )
							}
						/>
						{ __(
							'Analyse my existing content now (runs in the background)',
							'medora-authority'
						) }
					</label>
				) }

				{ error && <p className="medora-error">{ error }</p> }
			</div>

			<footer className="medora-wizard__foot">
				<button
					type="button"
					className="button"
					disabled={ step === 0 || submitting }
					onClick={ () => setStep( ( value ) => value - 1 ) }
				>
					{ __( 'Back', 'medora-authority' ) }
				</button>

				<span className="medora-muted">
					{ sprintf(
						/* translators: 1: current step, 2: total steps. */
						__( 'Step %1$d of %2$d', 'medora-authority' ),
						step + 1,
						steps.length
					) }
				</span>

				{ isLast ? (
					<button
						type="button"
						className="button button-primary"
						disabled={ submitting }
						onClick={ finish }
					>
						{ submitting
							? __( 'Setting up…', 'medora-authority' )
							: __( 'Finish setup', 'medora-authority' ) }
					</button>
				) : (
					<button
						type="button"
						className="button button-primary"
						onClick={ () => setStep( ( value ) => value + 1 ) }
					>
						{ __( 'Continue', 'medora-authority' ) }
					</button>
				) }
			</footer>
		</div>
	);
}
