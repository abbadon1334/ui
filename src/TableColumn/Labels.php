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
 * Class Labels
 *
 * take the fieldValue separated by commas and transforms in labels
 *
 * from => label1,label2 | to => div.ui.label[label1] div.ui.label[label2]
 *
 * @package atk4\ui\TableColumn
 */

class Labels extends Generic
{
    public function getHTMLTags($row, $field)
    {
        $values = explode(',', $field->get());
    
        $processed = [];
        foreach($values as $value)
        {
            $value = trim($value);
            
            if(!empty($value))
            {
                $processed[] = $this->app->getTag('div', ['class'=> "ui label"],$value);
            }
        }
    
        $processed = implode('',$processed);
        
        return [$field->short_name => $processed];
    }
}
