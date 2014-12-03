<?php

/*
 * This file is part of the Toran package.
 *
 * (c) Nelmio <hello@nelm.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toran\ProxyBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Toran\ProxyBundle\Model\Repository;
use Composer\Json\JsonFile;

class HomeController extends Controller
{
    public function indexAction(Request $req)
    {
        if (!file_exists($this->container->getParameter('toran_config_file'))) {
            return $this->redirect($this->generateUrl('setup'));
        }

        return $this->render('ToranProxyBundle:Home:index.html.twig', array('page' => 'home'));
    }

    protected function decodeJson($json)
    {
        $data = $err = null;
        try {
            $data = JsonFile::parseJson($json, 'satis config');
        } catch (\Exception $e) {
            $err = $e->getMessage();
            $err = preg_replace("{(Parse error [^\r\n]*)\r?\n(.*)}is", '$1<pre>$2</pre>', $err);
        }

        return array($data, $err);
    }

    protected function createConfigForm(Request $req, array $data = array())
    {
        $data = array_merge(array(
            'packagist_sync' => true,
            'dist_sync_mode' => 'lazy',
            'git_prefix' => '',
            'git_path' => '',
            'license' => '',
        ), $data);
        $data['packagist_sync'] = (bool) $data['packagist_sync'];

        $form = $this->createFormBuilder($data)
            ->add('packagist_sync', 'checkbox', array(
                'required' => false,
                'label' => 'Proxy packagist.org packages - enables the packagist proxy repository'
            ))
            ->add('dist_sync_mode', 'choice', array(
                'required' => true,
                'choices' => array(
                    'lazy' => 'Lazy: every archive is built on demand when you first install a given package\'s version',
                    'new' => 'New tags: tags newer than the oldest version you have used will be pre-cached as soon as they are available',
                    'all' => 'All: all releases will be pre-cached as they become available',
                ),
                'label' => 'Which zip archives should be pre-fetched by the cron job?',
                'expanded' => true,
            ))
            ->add('git_path', 'text', array(
                'required' => false,
                'label' => 'git path (where to store git clones on this machine, must be writable by the web user)',
                'attr' => array('placeholder' => '/home/git/mirrors/'),
            ))
            ->add('git_prefix', 'text', array(
                'required' => false,
                'label' => 'git prefix URL (how composer can remotely access the path above)',
                'attr' => array('placeholder' => 'git@' . $req->server->get('HOST') . ':mirrors/'),
            ))
            ->add('license_personal', 'checkbox', array(
                'required' => false,
                'label' => 'This instance is for personal use',
            ))
            ->add('license', 'textarea', array(
                'required' => false,
                'label' => 'License',
            ))
            ->add('satis_conf', 'textarea', array(
                'required' => false,
                'attr' => array('placeholder' => '{ "repositories": [ ... ] }'),
            ))
        ;

        return $form;
    }

    protected function processConfigForm($form, $config)
    {
        $data = $form->getData();
        $config->set('packagist_sync', $data['packagist_sync'] ? 'proxy' : false);
        $config->set('dist_sync_mode', $data['dist_sync_mode']);
        $config->set('git_prefix', $data['git_prefix'] ?: false);
        $config->set('git_path', $data['git_path'] ?: false);
        $config->set('license', $data['license']);
        $config->set('license_personal', $data['license_personal'] ?: false);

        if ((!$data['git_path'] && $data['git_prefix']) || ($data['git_path'] && !$data['git_prefix'])) {
            $form->addError(new FormError('Both git path and git prefix must be set or empty'));
        }
    }

    protected function validateLicense($form)
    {
        $data = $form->getData();
        if (empty($data['license']) && empty($data['license_personal'])) {
            $form->addError(new FormError('Missing license, you can <a href="http://toranproxy.com">buy one</a> or check the personal use box below if it applies'));
        }

        if (empty($data['license'])) {
            return;
        }

        $util = $this->get('toran_util');
        if (!$util->validateLicense($data['license'])) {
            $form->addError(new FormError('Invalid license'));
        }
    }
}
