function MultichoiceQuestionFormSubmit(questionform){
			var SELECTORS = {
				UNWANTEDHIDDENFIELDS: ":input[name='_qf__lesson_display_answer_form_multichoice_singleanswer']"
			};
			
			// We don't want to do a real form submission.
			//e.preventDefault();

			var form = $(questionform);
			
			// Before send the data through AJAX, we need to parse and remove some unwanted hidden fields.
			// This hidden fields are added automatically by mforms and when it reaches the AJAX we get an error.
			var hidden = form.find(SELECTORS.UNWANTEDHIDDENFIELDS);
			hidden.each(function() {
				$(this).remove();
			});

			var formData = form.serialize();
			var settings = {
				type: 'get',
				processData: false,
				contentType: "application/x-www-form-urlencoded"
			};

			var script = Config.wwwroot + '/mod/lesson/continueajax.php?' + formData+'&action=viewlesson';
			$.ajax(script, settings)
				.then(function(response) {
					if (response.msgerror) {
						Notification.addNotification({
							message: response.msgerror,
							type: "error"
						});
					} else {
						// Reload the page, don't show changed data warnings.
						if (typeof window.M.core_formchangechecker !== "undefined") {
							window.M.core_formchangechecker.reset_form_dirty_state();
						}
						
						if (response.newajaxcall) {//if php page have a redirect
								
								var settings = {
										type: 'get',
										processData: false,
										contentType: "application/x-www-form-urlencoded"
									};
								var script = response.ajaxredirect.replace(/&amp;/g, '&')+'&'+formData;
								$.ajax(script, settings)
									.then(function(response) {
										if (response.msgerror) {
											Notification.addNotification({
												message: response.msgerror,
												type: "error"
											});
										} else {
											//window.location.reload();
											
											if (response.newajaxcall) {//if php page have a redirect
													var settings = {
															type: 'get',
															processData: false,
															contentType: "application/htmlapplication/x-www-form-urlencoded",
															form: form
														};

														var script = response.ajaxredirect.replace(/&amp;/g, '&');
														$.ajax(script, settings)
															.then(function(response) {
																if (response.msgerror) {
																	Notification.addNotification({
																		message: response.msgerror,
																		type: "error"
																	});
																} else {
																	// Reload the page, don't show changed data warnings.
																	if (typeof window.M.core_formchangechecker !== "undefined") {
																		window.M.core_formchangechecker.reset_form_dirty_state();
																	}
																	//window.location.reload();
																	$('.lessoncontents').html(response.html);
																	}
																return;
															})
															.fail(Notification.exception);
												
											} else $('.lessoncontents').html(response.html);
										}
										return;
									})
									.fail(Notification.exception);
							
						} else $('.lessoncontents').html(response.html);
					}
					return;
				})
				.fail(Notification.exception);
		}
