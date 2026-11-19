(function($){

	var reload = function() {
		window.location.href = window.location.href;
	};

	var stop_process = function() {
		reload();
	};

	var restart_batch = function() {
		var nonce = DgBatchRunner.nonce;
		var batch = DgBatchRunner.batch_id;
		var ajax_url = DgBatchRunner.ajax_url;
		$.ajax({
			url: ajax_url + '?action=dg_restart_batch&nonce='+nonce,
			type: 'POST',
			data: {batch_id: batch},
			cache: false,
			// Reload only once the batch is cleared; a refused or failed request leaves it as it was.
			success: function(response) {
				if(response && response.success) {
					reload();
				} else {
					show_error(refusal_message(response));
				}
			},
			error: function() {
				show_error('The restart request failed.');
			}
		});
	};

	// A refused request (an expired nonce, a batch that is no longer registered) answers with a
	// message and no progress data. It is not a processed item, so it carries no is_finished.
	var is_refusal = function(response) {
		return !response || !response.data || typeof response.data.is_finished === 'undefined';
	};

	// A refusal's own message, or a general one when the body carries none.
	var refusal_message = function(response) {
		return (response && response.data && response.data.message) ? response.data.message : 'The request was refused.';
	};

	// Shows a message in red and adds it once to List of errors, escaped as text.
	var show_error = function(message) {
		$('.batch-process-current-item').html($('<span style="color: red;"></span>').text(message));
		$('#batch-errors').show();
		$('#batch-errors-list').append($('<li></li>').text(message));
	};

	// Shows a refusal's message once, stops requesting, and enables Start again.
	var stop_on_refusal = function(response) {
		show_error(refusal_message(response));
		$('#batch-process-start').text(DgBatchRunner.text.start).prop('disabled', false);
	};

	var process_next_item = function() {
		var nonce = DgBatchRunner.nonce;
		var batch = DgBatchRunner.batch_id;
		var ajax_url = DgBatchRunner.ajax_url;
		var delay = DgBatchRunner.delay;
		$.ajax({
			url: ajax_url + '?action=dg_process_next_batch_item&nonce='+nonce,
			type: 'POST',
			data: {batch_id: batch},
			cache: false,
			beforeSend: function() {
				$('#batch-process-start').text(DgBatchRunner.text.processing).prop('disabled', true);
			},
			success: function(response) {
				if(is_refusal(response)) {
					stop_on_refusal(response);
					return;
				}
				$(document).trigger('itemprocessed', [response])
				if(!response.data.is_finished) {
					if(delay > 0) {
						console.log('Waiting ' + DgBatchRunner.delay + ' seconds before processing next item.');
						setTimeout(function(){
							process_next_item();
						}, delay * 1000);
					} else {
						process_next_item();
					}
				} else {
					$('#batch-process-start').text(DgBatchRunner.text.start);
				}
			},
			error: function() {
				alert('HTTP Error.');
			},
		});

	}

	$(document).on('itemprocessed', function(e, response){
		var percentage = response.data.percentage;
		$('.batch-process-progress-bar-inner').css('width', percentage+'%');
		$('#batch-process-total').text(response.data.total_items);
		$('#batch-process-processed').text(response.data.total_processed);
		$('#batch-process-percentage').text('('+percentage+'%)');
		var color = response.success ? 'green' : 'red';
		var message = '<span style="color: '+color+';">'+response.data.message+'</span>';
		$('.batch-process-current-item').html(message);
		if(response.data.is_finished) {
			$('#batch-process-start, #batch-process-stop').prop('disabled', true);
		}
		if(!response.success) {
			$('#batch-errors').show();
			$('#batch-errors-list').append('<li>'+response.data.message+'</li>');
		}
	});

	$(document).on('click', '#batch-process-start', function(e){
		e.preventDefault();
		process_next_item();
	});

	$(document).on('click', '#batch-process-stop', function(e){
		e.preventDefault();
		stop_process();
	});

	$(document).on('click', '#batch-process-restart', function(e){
		e.preventDefault();
		restart_batch();
	});


})(jQuery);