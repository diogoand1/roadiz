<?php
/*
 * Copyright © 2015, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file DatabaseController.php
 * @author Ambroise Maupate
 */
namespace Themes\Install\Controllers;

use RZ\Roadiz\Console\Tools\Configuration;
use RZ\Roadiz\Console\Tools\YamlConfiguration;
use RZ\Roadiz\Console\Tools\Fixtures;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Themes\Install\InstallApp;

/**
 * DatabaseController
 */
class DatabaseController extends InstallApp
{
    /**
     * Install database screen.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function databaseAction(Request $request)
    {
        $config = new YamlConfiguration();
        if (false === $config->load()) {
            $config->setConfiguration($config->getDefaultConfiguration());
        }

        $databaseForm = $this->buildDatabaseForm($request, $config);

        if ($databaseForm !== null) {
            $databaseForm->handleRequest();

            if ($databaseForm->isValid()) {
                try {
                    $config->testDoctrineConnexion($databaseForm->getData());

                    $tempConf = $config->getConfiguration();
                    foreach ($databaseForm->getData() as $key => $value) {
                        $tempConf['doctrine'][$key] = $value;
                    }
                    $config->setConfiguration($tempConf);

                    /*
                     * Test connexion
                     */
                    try {
                        $fixtures = new Fixtures();
                        $fixtures->createFolders();

                        $config->writeConfiguration();

                        /*
                         * Force redirect to avoid resending form when refreshing page
                         */
                        $response = new RedirectResponse(
                            $this->getService('urlGenerator')->generate(
                                'installDatabaseSchemaPage'
                            )
                        );
                        $response->prepare($request);

                        return $response->send();
                    } catch (\PDOException $e) {
                        $message = "";
                        if (strstr($e->getMessage(), 'SQLSTATE[')) {
                            preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/', $e->getMessage(), $matches);
                            $message = $matches[3];
                        } else {
                            $message = $e->getMessage();
                        }
                        $this->assignation['error'] = true;
                        $this->assignation['errorMessage'] = ucfirst($message);
                    } catch (\Exception $e) {
                        $this->assignation['error'] = true;
                        $this->assignation['errorMessage'] = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
                    }

                } catch (\Exception $e) {
                    $this->assignation['error'] = true;
                    $this->assignation['errorMessage'] = $e->getMessage();
                }
            }
            $this->assignation['databaseForm'] = $databaseForm->createView();
        }

        return $this->render('steps/database.html.twig', $this->assignation);
    }

    /**
     * Perform database schema migration.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function databaseSchemaAction(Request $request)
    {
        /*
         * Test connexion
         */
        if (null === $this->getService('em')) {
            $this->assignation['error'] = true;
            $this->assignation['errorMessage'] = $c['session']->getFlashBag()->all();

        } else {
            try {
                \RZ\Roadiz\Console\SchemaCommand::createSchema();
                \RZ\Roadiz\Console\CacheCommand::clearDoctrine();

                /*
                 * Force redirect to install fixtures
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate(
                        'installDatabaseFixturesPage'
                    )
                );
                $response->prepare($request);

                return $response->send();

            } catch (\PDOException $e) {
                $message = "";
                if (strstr($e->getMessage(), 'SQLSTATE[')) {
                    preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/', $e->getMessage(), $matches);
                    $message = $matches[3];
                } else {
                    $message = $e->getMessage();
                }
                $this->assignation['error'] = true;
                $this->assignation['errorMessage'] = ucfirst($message);
            } catch (\Exception $e) {
                $this->assignation['error'] = true;
                $this->assignation['errorMessage'] = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            }
        }

        return $this->render('steps/databaseError.html.twig', $this->assignation);
    }

    /**
     * Perform database fixtures importation.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function databaseFixturesAction(Request $request)
    {
         $fixtures = new Fixtures();
         $fixtures->installFixtures();

         /*
          * files to import
          */
         $yaml = new YamlConfiguration(ROADIZ_ROOT . "/themes/Install/config.yml");

         $yaml->load();

         $installData = $yaml->getConfiguration();
         $this->assignation['imports'] = $installData['importFiles'];

         return $this->render('steps/databaseFixtures.html.twig', $this->assignation);
    }

    /**
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function updateSchemaAction(Request $request)
    {
        \RZ\Roadiz\Console\SchemaCommand::updateSchema();
        return new Response(
            json_encode(['status' => true]),
            Response::HTTP_OK,
            ['content-type' => 'application/javascript']
        );
    }

    /**
     * Build forms
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param RZ\Roadiz\Console\Tools\Configuration $conf
     *
     * @return Symfony\Component\Form\Forms
     */
    protected function buildDatabaseForm(Request $request, Configuration $conf)
    {
        if (isset($conf->getConfiguration()['doctrine'])) {
            $defaults = $conf->getConfiguration()['doctrine'];
        } else {
            $defaults = [];
        }

        $builder = $this->getService('formFactory')
                        ->createBuilder('form', $defaults)
                        ->add('driver', 'choice', [
                            'choices' => [
                                'pdo_mysql' => 'pdo_mysql',
                                'pdo_pgsql' => 'pdo_pgsql',
                                'pdo_sqlite' => 'pdo_sqlite',
                                'oci8' => 'oci8',
                            ],
                            'label' => $this->getTranslator()->trans('driver'),
                            'constraints' => [
                                new NotBlank(),
                            ],
                            'attr' => [
                                "id" => "choice",
                            ],
                        ])
            ->add('host', 'text', [
                "required" => false,
                'label' => $this->getTranslator()->trans('host'),
                'attr' => [
                    "autocomplete" => "off",
                    'id' => "host",
                ],
            ])
            ->add('port', 'integer', [
                "required" => false,
                'label' => $this->getTranslator()->trans('port'),
                'attr' => [
                    "autocomplete" => "off",
                    'id' => "port",
                ],
            ])
            ->add('unix_socket', 'text', [
                "required" => false,
                'label' => $this->getTranslator()->trans('unix_socket'),
                'attr' => [
                    "autocomplete" => "off",
                    'id' => "unix_socket",
                ],
            ])
            ->add('path', 'text', [
                "required" => false,
                'label' => $this->getTranslator()->trans('path'),
                'attr' => [
                    "autocomplete" => "off",
                    'id' => "path",
                ],
            ])
            ->add('user', 'text', [
                'attr' => [
                    "autocomplete" => "off",
                    'id' => "user",
                ],
                'label' => $this->getTranslator()->trans('username'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('password', 'password', [
                "required" => false,
                'label' => $this->getTranslator()->trans('password'),
                'attr' => [
                    "autocomplete" => "off",
                    'id' => 'password',
                ],
            ])
            ->add('dbname', 'text', [
                "required" => false,
                'label' => $this->getTranslator()->trans('dbname'),
                'attr' => [
                    "autocomplete" => "off",
                    'id' => 'dbname',
                ],
            ]);

        return $builder->getForm();
    }
}
