<?php

class figmaConverter{

    private $page_blocks = [];
    private $page_styles = [];
    private $errors = [];

    private function parseJson($filename){
        $json = file_get_contents($filename);
        $json_data = json_decode($json, true);

        return $json_data;
    }

    public function generateHtml($json_file){
        // lets parse our figma doc
        $figma_document = $this->parseJson($json_file);

        if(!isset($figma_document['document']) && !isset($figma_document['document']['children'])){
            $this->errors[] = 'Empty document or wrong format';
            return false;
        }

        $figma_pages = $figma_document['document']['children'];

        // get figma blocks on the page
        foreach($figma_pages as $figma_page){
            if(!isset($figma_page['children'])){
                $this->errors[] = "Page " . $figma_page['name'] . ' empty';
                continue;
            }

            foreach($figma_page['children'] as $figma_block){
                switch ($figma_block['type']){
                    case 'RECTANGLE':
                        $this->parseBlockRectangle($figma_block);
                    case 'TEXT':
                        // This is our main txt block
                        $this->parseBlockText($figma_block);
                }
            }
        }

        return [
            'html' => $this->page_blocks,
            //'style' => $this->cssArrayToCss($this->page_styles),
            'style' => json_encode($this->page_styles),
            'errors' => $this->errors,
        ];
        
    }

    private function parseBlockRectangle(){
        $this->errors[] = 'block  this is RECTANGLE - so skip it';
    }

    private function parseBlockText($figma_block){
        // get id block for use in html 
        $block_id = $this->attrFormater($figma_block['id']);

        // add style of this block
        if(isset($figma_block['absoluteBoundingBox'])){
            $this->page_styles[$block_id]['width'] = $figma_block['absoluteBoundingBox']['width'];
            $this->page_styles[$block_id]['height'] = $figma_block['absoluteBoundingBox']['width'];
        }

        if(isset($figma_block['style'])){
            foreach($figma_block['style'] as $style_property => $style_value){
                $this->page_styles[$block_id][$style_property] = strtolower($style_value);
            }
        }

        // work with text tag
        if(isset($figma_block['characters'])){
            $text = $figma_block['characters'];
            // check if we have some overrride
            if(isset($figma_block['characterStyleOverrides'])){
                // here we will get begin - end positions
                $override_map = $this->convertOverride($figma_block['characterStyleOverrides']);
                if(count($override_map)){
                    if(isset($figma_block['styleOverrideTable'])){
                        $search_list = [];
                        $replace_list = [];
                        
                        foreach($figma_block['styleOverrideTable'] as $style_override_id => $style_override){
                            if(isset($override_map[$style_override_id])){
                                // lets add span for overrided text
                                $span_positions = $override_map[$style_override_id];
                                
                                foreach($span_positions as $span_position){
                                    $orig_string = substr($text, $span_position['start'], $span_position['finish'] - $span_position['start'] + 1);
                                    $search_list[] = $orig_string;
                                    
                                    $replace_list[] = '<span class="' . $block_id . '-' . $style_override_id . '">' . $orig_string . '</span>';
                                }
                                // add new style for the spans
                                foreach($style_override as $style_property => $style_value){
                                    if($style_property == 'fills'){
                                        if(isset($style_value[0]['opacity'])){
                                            $this->page_styles[$block_id . '-' . $style_override_id]['opacity'] = $style_value[0]['opacity'];
                                        }
                                        if(isset($style_value[0]['color'])){
                                            $color_spectres = $style_value[0]['color'];
                                            $this->page_styles[$block_id . '-' . $style_override_id]['color'] = 'rgba(' . round($color_spectres['r'] * 255) . ', ' . round($color_spectres['g'] * 255) . ', ' . round($color_spectres['b'] * 255) . ', ' . $color_spectres['a'] . ')';
                                        }
                                    }elseif($style_property == 'italic'){
                                        $this->page_styles[$block_id . '-' . $style_override_id]['font-style'] = 'italic';
                                    }elseif($style_property == 'textDecoration' && $style_value == 'STRIKETHROUGH'){
                                        $this->page_styles[$block_id . '-' . $style_override_id]['text-decoration'] = 'line-through';    
                                    }else{
                                        $this->page_styles[$block_id . '-' . $style_override_id][$style_property] = $style_value;
                                    }
                                }
                            }
                        }
                        
                        if(count($search_list)){
                            $text = nl2br(str_replace($search_list, $replace_list, $text));
                        }
                    }
                }
            }
            $this->page_blocks[] = '<div class="' . $block_id .'">' . $text . '</div>';
        }
    }

    public function cssArrayToCss($rules, $indent = 0) {
        $css = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($rules as $key => $value) {
            if (is_array($value)) {
                $selector = $key;
                $properties = $value;

                $css .= $prefix . "$selector {\n";
                $css .= $prefix . $this->cssArrayToCss($properties, $indent + 1);
                $css .= $prefix . "}\n";
            } else {
                $property = $key;
                $css .= $prefix . "$property: $value;\n";
            }
        }

        return $css;
    }

    private function attrFormater($str){
        return str_replace(':', '-', $str);
    }

    private function convertOverride($override_map){
        $raw_map = [];

        foreach($override_map as $map_key => $map_val){
            $raw_map[$map_val][] = $map_key;
        }

        $clean_map = [];
        $count = 0;
        $last_position = 0;
       
        foreach($raw_map as $override_id => $position){
            foreach($position as $position_item){
                if(!isset($clean_map[$override_id][$count])){
                    $clean_map[$override_id][$count]['start'] = $position_item;
                }else{
                    if($last_position + 1 == $position_item){
                        $clean_map[$override_id][$count]['finish'] = $position_item;
                    }else{
                        $count ++;
                        $clean_map[$override_id][$count]['start'] = $position_item;
                    }
                }
                $last_position = $position_item;
            }
        }

        return $clean_map;
    }
}