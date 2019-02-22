<?php
/**
 * Copyright (c) 2019.
 *
 * Francesco "Abbadon1334" Danti <fdanti@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

namespace atk4\ui\TableColumn;

/**
 * Class Tooltip
 *
 * column to add a little icon to show on hover a text
 * text is taken by the Model in $tooltip_field
 *
 * @usage : $crud->addDecorator('paid_date',  new \atk4\ui\TableColumn\Tooltip('note'));
 *
 * @usage : $crud->addDecorator('paid_date',  new \atk4\ui\TableColumn\Tooltip('note','error circle red'));
 *
 * @package atk4\ui\TableColumn
 */

class Tooltip extends Generic
{
    /**
     *
     * @var string $field_tooltip
     */
    public $icon = [];
    
    /**
     *
     * @var string $tooltip_field
     */
    public $tooltip_field = [];
    
    /**
     * Pass argument of tooltip field
     *
     * @param string $field_tooltip
     * @param string $icon
     */
    public function __construct($field_tooltip = null, $icon = 'info circle blue')
    {
        $this->tooltip_field = $field_tooltip;
        $this->icon          = $icon;
    }
    
    public function getDataCellHTML(\atk4\data\Field $f = null, $extra_tags = [])
    {
        if ($f === null) {
            throw new Exception(['Tooltip can be used only with model field']);
        }
    
        $attr = $this->getTagAttributes('body');
    
        $extra_tags = array_merge_recursive($attr, $extra_tags, ['class' => '{$_'.$f->short_name.'_tooltip}']);
    
        if (isset($extra_tags['class']) && is_array($extra_tags['class'])) {
            $extra_tags['class'] = implode(' ', $extra_tags['class']);
        }
        
        return $this->app->getTag(
            'td',
            $extra_tags,
            [
                ' {$'.$f->short_name.'}' .
                $this->app->getTag(
                    'span',
                    [
                        'class'=>'ui icon link {$_'.$f->short_name.'_data_visible_class}',
                        'data-tooltip' => '{$_'.$f->short_name.'_data_tooltip}'
                    ],
                    [
                        ['i', ['class'=>'ui icon {$_'.$f->short_name.'_icon}']]
                    ]
                )
            ]
        );
    }
    
    public function getHTMLTags($row, $field)
    {
        // @TODO remove popup tooltip when null
        $tooltip = $row->data[$this->tooltip_field];
        
        if(is_null($tooltip) || empty($tooltip) ) {
            
            return [
                '_'.$field->short_name.'_data_visible_class' => 'transition hidden',
                '_'.$field->short_name.'_data_tooltip' => '',
                '_'.$field->short_name.'_icon'   => '',
            ];
        }
        
        return [
            '_'.$field->short_name.'_data_visible_class' => '',
            '_'.$field->short_name.'_data_tooltip' => $tooltip,
            '_'.$field->short_name.'_icon'   => $this->icon,
        ];
    }
}
