const MAILPIT_URL = process.env.MAILPIT_URL || 'http://localhost:8025';

export interface MailpitMessage {
  ID: string;
  Subject: string;
  To: Array<{ Address: string; Name: string }>;
  From: { Address: string; Name: string };
  Text: string;
  HTML: string;
}

export async function getLatestEmail(
  toAddress: string,
  timeoutMs = 15_000
): Promise<MailpitMessage> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const res = await fetch(`${MAILPIT_URL}/api/v1/messages`);
    if (!res.ok) throw new Error(`Mailpit API error: ${res.status}. Is Mailpit running?`);
    const data = await res.json();
    const messages: MailpitMessage[] = data.messages || [];
    const match = messages.find((m) =>
      m.To.some((t) => t.Address.toLowerCase() === toAddress.toLowerCase())
    );
    if (match) {
      const full = await fetch(`${MAILPIT_URL}/api/v1/message/${match.ID}`);
      return (await full.json()) as MailpitMessage;
    }
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error(`No email found for ${toAddress} within ${timeoutMs}ms. Is Mailpit running?`);
}

export async function clearMailpit(): Promise<void> {
  await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE' });
}

// Extracts href from <a>linkText</a> in email HTML
// Used to pull cancel/reschedule magic link URLs
export function extractLinkFromEmail(html: string, linkText: string): string {
  const escaped = linkText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`<a[^>]+href="([^"]+)"[^>]*>\\s*${escaped}\\s*<\\/a>`, 'i');
  const match = html.match(regex);
  if (!match) throw new Error(`Link "${linkText}" not found in email HTML`);
  return match[1];
}
