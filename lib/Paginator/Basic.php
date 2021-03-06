<?php // vim:ts=4:sw=4:et:fdm=marker
/*
==ATK4===================================================
   This file is part of Agile Toolkit 4
    http://agiletoolkit.org/

   (c) 2008-2013 Agile Toolkit Limited <info@agiletoolkit.org>
   Distributed under Affero General Public License v3 and
   commercial license.

   See LICENSE or LICENSE_COM for more information
 =====================================================ATK4=*/
/**
 * Paginator needs to have source set (which can be either Model,
 * DSQL or Array). It will render itself into parent and will
 * limit the source to display limited number of records per page
 * with ability to travel back and forth
 */
class Paginator_Basic extends CompleteLister {
    public $ipp=30;         // By default, show 30 records per page
    public $skip=0;         // By default, do not skip anything
    public $range=4;        // Display 4 adjacent pages from current one

    public $ajax_reload=true;   // Reload parent with AJAX
    public $memorize=true;      // Remember page, when user comes back
    public $skip_var=null;      // Argument to use to specify page

    public $source=null;        // Set with setSource()
    public $base_page=null;     // let's redefine page nicely

    function init(){
        parent::init();
        if(!$this->skip_var)$this->skip_var=$this->name.'_skip';
    }

    /** Set number of items displayed per page */
    function ipp($ipp){
        $this->ipp=$ipp;
        return $this;
    }
    /** Set a custom source. Must be an object with foundRows() method */
    function setSource($source){
        if($this->memorize){
            $this->skip=$this->memorize('skip', @$_GET[$this->skip_var])+0;
        }else{
            $this->skip=@$_GET[$this->skip_var]+0;
        }
        if($source instanceof Model_Table){

            // Start iterating early
            $source = $source->_preexec();

            $source->limit($this->ipp,$this->skip);
            $source->calcFoundRows();

            $this->source=$source;

        }elseif($source instanceof Model){
            $this->source=$source->setLimit($this->ipp,$this->skip);
        }elseif($source instanceof DB_dsql){
            $source->_dsql()->calcFoundRows();

        }else{
            $this->source =& $source;
        }
    }
    function recursiveRender(){

        if(!$this->source){
            if($this->owner->model){
                if($this->owner instanceof Grid_Advanced) $this->owner->getIterator(); // force grid->model sorting implemented in Grid_Advanced
                $this->setSource($this->owner->model);
            }
        }

        if(!isset($this->source))
            throw $this->exception('Unable to find source for Paginator');

        if($this->source instanceof DB_dsql){
            $this->source->preexec();
            $this->found_rows=$this->source->foundRows();
        }elseif($this->source instanceof Mongo_Model){
            $this->found_rows=$this->source->count();
        }else{
            $this->found_rows=count($this->source);
        }

        $this->cur_page=floor($this->skip / $this->ipp) +1;
        $this->total_pages = ceil($this->found_rows / $this->ipp);

        if($this->cur_page>$this->total_pages || ($this->cur_page==1 && $this->skip!=0)){
            $this->cur_page=1;
            if($this->memorize){
                $this->memorize('skip',$this->skip=0);
            }
            if($this->source instanceof DB_dsql){
                $this->source->limit($this->ipp,$this->skip);
                $this->source->rewind();                 // re-execute the query
            }else{
                $this->source->setLimit($this->ipp,$this->skip);
            }
        }

        if($this->total_pages<=1)return $this->destroy();

        if($this->cur_page>1){
            $this->add('View',null,'prev')
                ->setElement('a')
                ->setAttr('href',$this->api->url($this->base_page,$u=array($this->skip_var=>
                    $pn=max(0,$this->skip-$this->ipp)
                )))
                ->setAttr('data-skip',$pn)
                ->set('<')
                ;
        }
        
        if($this->cur_page<$this->total_pages){
            $this->add('View',null,'next')
                ->setElement('a')
                ->setAttr('href',$this->api->url($this->base_page,$u=array($this->skip_var=>
                    $pn=$this->skip+$this->ipp
                )))
                ->setAttr('data-skip',$pn)
                ->set('>')
                ;
        }

        // First page
        if($this->cur_page>$this->range+1){
            $this->add('View',null,'first')
                ->setElement('a')
                ->setAttr('href',$this->api->url($this->base_page,$u=array($this->skip_var=>
                    $pn=max(0,0)
                )))
                ->setAttr('data-skip',$pn)
                ->set('1')
                ;
            if($this->cur_page>$this->range+2){
                $this->add('View',null,'points_left')
                    ->setElement('span')
                    ->set('...')
                    ;
            }
        }

        // Last page
        if($this->cur_page<$this->total_pages-$this->range){
            $this->add('View',null,'last')
                ->setElement('a')
                ->setAttr('href',$this->api->url($this->base_page,$u=array($this->skip_var=>
                    $pn=max(0,($this->total_pages-1)*$this->ipp)
                )))
                ->setAttr('data-skip',$pn)
                ->set($this->total_pages)
                ;
            if($this->cur_page<$this->total_pages-$this->range-1){
                $this->add('View',null,'points_right')
                    ->setElement('span')
                    ->set('...')
                    ;
            }
        }
        
        // generate our source now
        $data=array();

        foreach(range(max(1,$this->cur_page-$this->range), min($this->total_pages, $this->cur_page+$this->range)) as $p){
            $data[]=array(
                'href'=>$this->api->url($this->base_page,array($this->skip_var=>$pn=($p-1)*$this->ipp)),
                'pn'=>$pn,
                'cur'=>$p==$this->cur_page?$this->template->get('cur'):'',
                'label'=>$p
            );
        } 

        if($this->ajax_reload){
            $this->js('click',$this->owner->js()->reload(array($this->skip_var=>$this->js()->_selectorThis()->attr('data-skip'))))->_selector('#'.$this->name.' a');
        }


        parent::setSource($data);
        return parent::recursiveRender();

    }
    function defaultTemplate(){
        return array('paginator42','paginator');
    }
    function defaultSpot(){
        return 'paginator';
    }
}
