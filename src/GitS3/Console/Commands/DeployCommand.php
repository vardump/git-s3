<?php namespace GitS3\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo as File;
use GitS3\Wrapper\Diff;


class DeployCommand extends Command {

	private $output;
	private $finder;

	protected function configure() {
		$this->setName('deploy');
		$this->setDescription('Deploy the current git repo');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$application = $this->getApplication();

		$this->output = $output;
		$this->bucket = $application->getBucket();
		$this->finder = new Finder();
		$this->finder->files()->in($application->getRepositoryPath());

		if ($this->hasNotBeenInitialized()) {
			$this->init();
			$application->writeLastDeploy();
			$output->writeln('Lock file initialized. Deployment complete!');
		} elseif ($this->isUpToDate()) {
			$output->writeln('Already up-to-date.');
		} else {
			$this->deployCurrentCommit();
			$application->writeLastDeploy();
			$output->writeln('Lock file updated. Deployment complete!');
		}
	}

	private function isUpToDate()
	{
		return $this->getApplication()->getIsUpToDate();
	}

	private function hasNotBeenInitialized()
	{
		return $this->getApplication()->getHashOfLastDeploy() == '';
	}

	private function init()
	{
		foreach ($this->finder as $file) {
			$this->uploadFile($file);
		}
	}

	private function deployCurrentCommit()
	{
		$application = $this->getApplication();
		$diff = new Diff($application->getRepository(), $application->getHashOfLastDeploy());

		$filesToUpload = $diff->getFilesToUpload();
		$filesToDelete = $diff->getFilesToDelete();

		foreach ($this->finder as $file) {
			if (in_array($file->getRelativePathname(), $filesToUpload)) {
				$this->uploadFile($file);
			}
		}

		foreach ($filesToDelete as $fileName) {
			$this->deleteFile($fileName);
		}
	}

	private function uploadFile(File $file)
	{
		$this->output->writeln('Uploading ' . $file->getRelativePathname());
		$this->bucket->upload($file);
	}

	private function deleteFile($fileName)
	{
		$this->output->writeln('Deleting ' . $fileName);
		$this->bucket->delete($fileName);
	}
}