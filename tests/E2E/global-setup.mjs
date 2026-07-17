import { execFileSync } from 'node:child_process';

export default async function globalSetup() {
  const env = execFileSync('php', ['artisan', 'env'], { encoding: 'utf8' });
  if (/production/i.test(env)) {
    throw new Error('E2E fixture setup is blocked in production.');
  }

  execFileSync('php', ['artisan', 'db:seed', '--class=AdminSeeder', '--force'], { stdio: 'inherit' });
  execFileSync('php', ['artisan', 'db:seed', '--class=DemoUserSeeder', '--force'], { stdio: 'inherit' });
  execFileSync('php', ['artisan', 'pkpa:e2e-prepare'], { stdio: 'inherit' });
}
