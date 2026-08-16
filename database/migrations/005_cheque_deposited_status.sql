-- Make the banking step explicit.  Deposited cheques remain receivables until
-- they clear, but the owner can now see which ones are already at the bank.
ALTER TABLE cheques
    MODIFY COLUMN status ENUM('pending','deposited','cleared','bounced','cancelled')
        NOT NULL DEFAULT 'pending';
