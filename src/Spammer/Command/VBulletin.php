<?php

namespace Spammer\Command;

use Goutte\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VBulletin extends Command
{
    const LOGIN_PATH        = 'login.php';
    const RSS_PATH          = 'external.php?type=RSS2';
    const POST_PATH         = 'showthread.php?t=%d';

    protected $base;
    protected $username;
    protected $password;
    protected $message;

    /**
     * @var Client
     */
    protected $client;

    protected function configure()
    {
        $this->setName("spammer:vbulletin")
            ->setDescription("Spammer for VBulletin site")
            ->addArgument(
                'base',
                InputArgument::REQUIRED,
                'Base VBulletin url (http://vbulletinsite.com/)'
            )
            ->addOption(
                'username',
                'u',
                InputOption::VALUE_REQUIRED,
                'Your username'
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                'Your password'
            )->addOption(
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'Message file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->base = $input->getArgument('base');

        if ('/' !== substr($this->base, -1)) {
            $this->base .= '/';
        }

        $this->username = $input->getOption('username');
        $this->password = $input->getOption('password');

        $this->message = file_get_contents($input->getOption('message'));

        if (! $this->login($output)) {
            return ;
        }

        if (! $lastTopic = $this->getLastTopic($output)) {
            return ;
        }

        for ($i = $lastTopic; $i >= 1; $i--) {
            $this->createNewPost($output, $i);
        }

        $output->writeln("<fg=blue>Finish</fg=blue>");

        return ;
    }

    private function login(OutputInterface $output)
    {
        $this->client = new Client();

        $output->writeln("<fg=blue>Try to login...</fg=blue>");

        $crawler = $this->client->request(
            'POST',
            $this->base . self::LOGIN_PATH,
            [
                'do'                   => 'login',
                'cookieuser'           => 1,
                'vb_login_username'    => $this->username,
                'vb_login_md5password' => md5($this->password)
            ]
        );

        if (! stristr($crawler->html(), $this->username)) {
            $output->writeln('<error>Invalid username or password</error>');

            return false;
        }

        $output->writeln('<info>Login OK</info>');
        $output->writeln(sprintf("<comment>Logged with:</comment> <info>%s</info> \n", $this->username));

        return true;
    }

    private function getLastTopic(OutputInterface $output)
    {
        $output->writeln("<fg=blue>Find last topic id...</fg=blue>");

        $crawler = $this->client->request(
            'GET',
            $this->base . self::RSS_PATH
        );

        $lastTopicUrl = $crawler->filter('channel item')->first()->filter('link')->text();

        $output->writeln(sprintf("<comment>Last topic is:</comment> <info>%s</info>", $lastTopicUrl));

        $crawler = $this->client->request(
            'GET',
            $lastTopicUrl
        );

        if (! preg_match('/var RELPATH = "showthread\.php\?t=(.*)";/', $crawler->html(), $matches)) {
            $output->writeln('<error>Error to get last topic</error>');
            return false;
        }

        $output->writeln(sprintf("<comment>Topic ID:</comment> <info>%d</info>\n", $matches[1]));

        return $matches[1];
    }

    private function createNewPost(OutputInterface $output, $postId)
    {
        $postUrl = sprintf($this->base . self::POST_PATH, $postId);

        $output->writeln(sprintf('<fg=blue>Request %s...</fg=blue>', $postUrl));

        $crawler = $this->client->request(
            'GET',
            $postUrl
        );

        if (404 == $this->client->getResponse()->getStatus()) {
            $output->writeln(sprintf("<error>Post %d not found</error>", $postId));
        }

        $output->writeln('<info>Post found, posting message...</info>');
        try {
            $form = $crawler->filter('form[name=quick_reply]')->form();
        } catch(\Exception $e) {
            $output->writeln("<error>Post is closed.</error> \n");
            return false;
        }

        $this->client->submit($form, [
            'message' => $this->message
        ]);

        if (200 !== $this->client->getResponse()->getStatus()) {
            $output->writeln("<error>Error on creating the message.</error> \n");
            return false;
        }

        $output->writeln("<info>Message OK</info> \n");
    }
}