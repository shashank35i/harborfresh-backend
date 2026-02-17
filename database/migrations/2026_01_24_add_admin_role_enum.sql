-- Expand users.role enum to include admin
ALTER TABLE `users`
  MODIFY `role` enum('user','seller','admin') DEFAULT 'user';
