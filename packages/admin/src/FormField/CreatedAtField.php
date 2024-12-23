<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;

/**
 * @template T of object
 *
 * @extends AbstractField<T>
 */
class CreatedAtField extends AbstractField
{
    final public const array DateTimePickerOptions = [
        'useCurrent' => true,
        'display' => [
            'viewMode' => 'calendar',
            'components' => ['seconds' => false],
        ],
    ];

    final public const string DateTimePickerFormat = 'yyyy-MM-dd HH:mm';

    /**
     * @param FormMapper<T> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('createdAt', DateTimePickerType::class, [
            'format' => self::DateTimePickerFormat,
            'datepicker_options' => self::DateTimePickerOptions,
            'label' => $this->formFieldManager->getMessagePrefix().'.createdAt.label',
        ]);
    }
}

/*

            'dp_side_by_side' => true,
            'dp_collapse' => true,
            'dp_calendar_weeks' => false,
            'dp_view_mode' => 'days',
            'dp_min_view_mode' => 'days',

"auto_initialize", "block_name", "block_prefix", "by_reference", "choice_translation_domain", "compound", "constraints", "csrf_field_name", "csrf_message", "csrf_protection", "csrf_token_id", "csrf_token_manager", "data", "data_class", "date_format", "date_label", "date_widget", "datepicker_options", "datepicker_use_button", "days", "disabled", "empty_data", "error_bubbling", "error_mapping", "extra_fields_message", "form_attr", "format", "getter", "help", "help_attr", "help_html", "help_translation_parameters", "horizontal_input_wrapper_class", "horizontal_label_class", "horizontal_label_offset_class", "hours", "html5", "inherit_data", "input", "input_format", "invalid_message", "invalid_message_parameters", "is_empty_callback", "label", "label_attr", "label_format", "label_html", "label_render", "label_translation_parameters", "mapped", "method", "minutes", "model_timezone", "months", "placeholder", "post_max_size_message", "priority", "property_path", "required", "row_attr", "seconds", "setter", "sonata_admin", "sonata_field_description", "time_label", "time_widget", "translation_domain", "trim", "upload_max_size_message", "validation_groups", "view_timezone", "widget", "with_minutes", "with_seconds", "years".

*/
