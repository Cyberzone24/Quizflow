$(document).ready(function() {
    // radio button styling / check correct answer
    $('input[type=radio]').click(function() {
        var fieldset = $(this).closest('fieldset');
        fieldset.find('.option-label').removeClass('border-blue-500');
        if ($(this).is(':checked')) {
            // remove the highlight of previously selected answers
            fieldset.find('.option-label').removeClass('border-green-500 border-red-500 bg-gray-200');

            // highlight selected answer
            var selectedLabel = $('label[for=' + $(this).attr('id') + ']');
            var isCorrect = String($(this).data('correct')) === 'true'; // convert to string before comparing
            if (isCorrect) {
                selectedLabel.addClass('border-green-500');
            } else {
                selectedLabel.addClass('border-red-500');
            }

            // highlight selected answer
            selectedLabel.addClass('bg-gray-200');

            // highlight in-/correct answer
            fieldset.find('input[type=radio]').not(this).each(function() {
                var label = $('label[for=' + $(this).attr('id') + ']');
                if (String($(this).data('correct')) === 'true') {
                    label.addClass('border-green-500');
                } else {
                    label.addClass('border-red-500');
                }
            });
        }
    });

    // show/hide questions
    var currentQuestion = 0;
    var totalQuestions = $('fieldset').length;

    function showQuestion(index) {
        if ($(window).width() <= 768) {
            $('fieldset').hide();
            $('fieldset').eq(index).show();
        }

        if (index === 0) {
            $('#prev').css('visibility', 'hidden');
        } else {
            $('#prev').css('visibility', 'visible');
        }

        if (index === totalQuestions - 1) {
            $('#next').attr('type', 'submit').text('Absenden');
        } else {
            $('#next').attr('type', 'button').text('Weiter');
        }
    }

    $('#prev').click(function() {
        if (currentQuestion > 0) {
            currentQuestion--;
            showQuestion(currentQuestion);
        }
    });

    $('#next').click(function() {
        if (currentQuestion < totalQuestions - 1) {
            currentQuestion++;
            showQuestion(currentQuestion);
        } else if ($(this).attr('type') === 'submit') {
            // Enable all radio buttons before submitting the form
            $('input[type=radio]').prop('disabled', false);
            $('#quizForm').submit();
        }
    });

    // Update the submit button to only call a JavaScript function
    $('#quizForm').on('submit', function(e) {
        e.preventDefault();
        $('input[type=radio]').prop('disabled', false);
        this.submit();
    });

    // progress bar
    showQuestion(currentQuestion);

    var totalQuestions = $('fieldset').length - 1;
    var progressBar = $('.progress-bar');

    function updateProgressBar() {
        var answeredQuestions = $('input[type=radio]:checked').length;
        var progress = (answeredQuestions / totalQuestions) * 100;
        progressBar.css('width', progress + '%');
    }

    $('#quizForm').on('click', 'input[type=radio]', updateProgressBar);
});