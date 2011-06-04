<?php

/**
 * captcha actions.
 *
 * @package    captcha
 * @subpackage captcha
 * @author     Your name here
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class captchaActions extends sfActions
{
    public function executeGetImage()
    {
        $this->image = $this->generateCaptchaImage();
	$this->setLayout(false);
    }

    public function executeDemo()
    {
        $this->form = new DemoForm();
        if( $request->isMethod('post'))
        {
            $hash = $request->getParameter('captcha_hash');
            $word = $request->getParameter('captcha_word');
            $params = $request->getParameter('demo_form');
            $this->form->bind($params);
            $this->captchaError = !procaptcha_verify($hash, $word);
            if($this->form->isValid() and !$this->captchaError)
            {
                return 'Complete';
            }
        }
	    $this->getResponse()->addStylesheet('procaptcha-default');
    }

    public function executeValidation(sfWebRequest $request)
    {
        $hash = $request->getParameter('hash');
        $word = $request->getParameter('word');
        $validation = Doctrine_Core::getTable('Validation')->findOneByHash($hash);
        $wordObject = Doctrine_Core::getTable('Word')->findOneByName($word);
        $this->forward404unless($validation);
        if($wordObject)
        {
        $synonymGroup = Doctrine_Core::getTable('SynonymGroup')->findOneById($validation->getSynonymGroupId());
        $this->result = Doctrine_Query::create()
            ->select('word_id')
            ->from('WordSynonymGroup')
            ->where("synonym_group_id=$synonymGroup->id")
            ->andWhere("word_id=$wordObject->id")
            ->count();
        }
        else
        {
            $this->result = false;
        }
        $this->forward404Unless($this->result);
    }

    public function executeGetCaptcha()
    {
        $this->hash = $this->generateCaptchaImage();
        $this->image = '/images/captcha/'.$this->hash.'.png';
	$this->setLayout(false);
    }

    public function generateCaptchaImage()
    {
        $groupCount = SynonymGroupTable::getInstance()->count();
        $randGroupNumber = rand(1, $groupCount);
        $wordCount = Doctrine_Query::create()
            ->select('word_id')
            ->from('WordSynonymGroup')
            ->where("synonym_group_id=$randGroupNumber")
            ->count();
        $newImage = imagecreatetruecolor(sfConfig::get('app_images_width')*5, sfConfig::get('app_images_height'));
        $alreadyUsed = array();
        for($i = 0; $i < 5; $i++)
        {
            $randImageNumber = rand(1, sfConfig::get('app_images_perWord')*$wordCount);
            while(in_array($randImageNumber, $alreadyUsed)) {
                $randImageNumber = rand(1, sfConfig::get('app_images_perWord')*$wordCount);
            }
            $src = imagecreatefrompng(sfConfig::get('sf_root_dir').'/web/images/cache/'.$randGroupNumber.'_'.$randImageNumber.'.'.sfConfig::get('app_images_filetype'));
            imagecopy($newImage, $src, $i*sfConfig::get('app_images_width'), 0, 0, 0, imagesx($src), imagesy($src));
            imagedestroy($src);
            $alreadyUsed[] = $randImageNumber;
        }
        $hash = md5(date('dmYhis'));
        imagepng($newImage, sfConfig::get('sf_root_dir').'/web/images/captcha/'.$hash.'.png', 9);
        $validation = new Validation();
        $validation->setHash($hash);
        $validation->setSynonymGroupId($randGroupNumber);
        $validation->save();
        return $hash;
    }

  public function executeIndex(sfWebRequest $request)
  {
    $this->synonym_groups = Doctrine_Core::getTable('SynonymGroup')
      ->createQuery('a')
      ->execute();
  }

  public function executeNew(sfWebRequest $request)
  {
    $this->form = new SynonymGroupForm();
  }

  public function executeCreate(sfWebRequest $request)
  {
    $this->forward404Unless($request->isMethod(sfRequest::POST));

    $this->form = new SynonymGroupForm();

    $this->processForm($request, $this->form);

    $this->setTemplate('new');
  }

  public function executeEdit(sfWebRequest $request)
  {
    $this->forward404Unless($synonym_group = Doctrine_Core::getTable('SynonymGroup')->find(array($request->getParameter('id'))), sprintf('Object synonym_group does not exist (%s).', $request->getParameter('id')));
    $this->form = new SynonymGroupForm($synonym_group);
  }

  public function executeUpdate(sfWebRequest $request)
  {
    $this->forward404Unless($request->isMethod(sfRequest::POST) || $request->isMethod(sfRequest::PUT));
    $this->forward404Unless($synonym_group = Doctrine_Core::getTable('SynonymGroup')->find(array($request->getParameter('id'))), sprintf('Object synonym_group does not exist (%s).', $request->getParameter('id')));
    $this->form = new SynonymGroupForm($synonym_group);

    $this->processForm($request, $this->form);

    $this->setTemplate('edit');
  }

  public function executeDelete(sfWebRequest $request)
  {
    $request->checkCSRFProtection();

    $this->forward404Unless($synonym_group = Doctrine_Core::getTable('SynonymGroup')->find(array($request->getParameter('id'))), sprintf('Object synonym_group does not exist (%s).', $request->getParameter('id')));
    $synonym_group->delete();

    $this->redirect('captcha/index');
  }

  protected function processForm(sfWebRequest $request, sfForm $form)
  {
    $form->bind($request->getParameter($form->getName()), $request->getFiles($form->getName()));
    if ($form->isValid())
    {
      $synonym_group = $form->save();

      $this->redirect('captcha/edit?id='.$synonym_group->getId());
    }
  }
}
