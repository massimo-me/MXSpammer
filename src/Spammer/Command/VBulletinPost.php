<?php

namespace Spammer\Command;

use Goutte\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VBulletinPost extends Command
{
    const LOGIN_PATH             = 'login.php';
    const RSS_PATH               = 'external.php?type=RSS2';
    const POST_PATH              = 'showthread.php?t=%d';
    const NEW_REPLY_FORM_PATH    = 'newreply.php?do=newreply&t=%d&noquote=1';
    const NEW_REPLY_PATH         = 'newreply.php?do=postreply&t=%d';

    protected $base;
    protected $username;
    protected $password;
    protected $message;
    protected $postTitle;
    protected $lastTopic;

    /**
     * @var Client
     */
    protected $client;

    protected function configure()
    {
        $this->setName("spammer:vbulletin:post")
            ->setDescription("Post Spammer for VBulletin site")
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
            )
            ->addOption(
                'last-topic',
                'l',
                InputOption::VALUE_REQUIRED,
                'Last topic'
            )
            ->addOption(
                'post-title',
                't',
                InputOption::VALUE_REQUIRED,
                'Post title'
            )
            ->addOption(
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
        $this->postTitle = $input->getOption('post-title');
        $this->lastTopic = $input->getOption('last-topic');

        if (! $this->login($output)) {
            return ;
        }

        if (! $this->lastTopic) {
            if (! $this->lastTopic = $this->getLastTopic($output)) {
                return ;
            }
        }

        $output->writeln(sprintf("<comment>Start from Topic ID:</comment> <info>%d</info>\n", $this->lastTopic));

        for ($i = $this->lastTopic; $i >= 1; $i--) {
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

        if (preg_match('/(showthread\.php)/', $lastTopicUrl)) {
            $queries = $this->getQueryStringFromUrl($lastTopicUrl);

            if (array_key_exists('t', $queries)) {

                return $queries['t'];
            }
        }

        $crawler = $this->client->request(
            'GET',
            $lastTopicUrl
        );

        if (! preg_match('/<a href="((?=.*\bsubscription\.php\b).*)"\s.+<\/a>/', $crawler->html(), $matches)) {
            $output->writeln('<error>Error to get last topic, plase use --last-topic option</error>');

            return false;
        }

        foreach ($this->getQueryStringFromUrl($matches[1]) as $param) {
            if (strstr($lastTopicUrl, $param)) {

                return $param;
            }
        }

        $output->writeln('<error>Error to get last topic, plase use --last-topic option</error>');

        return false;
    }

    private function createNewPost(OutputInterface $output, $postId)
    {
        $newPostUrl = sprintf($this->base . self::NEW_REPLY_FORM_PATH, $postId);

        $output->writeln(sprintf('<fg=blue>Request %s...</fg=blue>', $newPostUrl));

        $crawler = $this->client->request('GET', $newPostUrl);

        if (404 == $this->client->getResponse()->getStatus()) {
            $output->writeln(sprintf("<error>Post %d not found</error> \n", $postId));
        }

        $output->writeln('<info>Post found, posting message...</info>');

        //Get SecurityToken
        $securityToken = $crawler->filter('input[name="securitytoken"]')->attr('value');

        if (! $securityToken) {
            $output->writeln("<error>Unable to receive vbulletin security token</error> \n");

            return false;
        }

        $this->client->request('POST', $this->base. sprintf(self::NEW_REPLY_PATH, $postId), [
            'title'          => ($this->postTitle) ? $this->postTitle : null,
            'message'        => $this->message,
            'securitytoken'  => $securityToken,
            'do'             => 'postreply',
        ]);

        if (200 !== $this->client->getResponse()->getStatus()) {
            $output->writeln("<error>Error on creating the message.</error> \n");
            return false;
        }

        $output->writeln(sprintf("<info>Message OK, visit %s</info> \n", (sprintf($this->base . self::POST_PATH, $postId))));

        return true;
    }

    private function getQueryStringFromUrl($url)
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $parameters);

        return $parameters;
    }
}