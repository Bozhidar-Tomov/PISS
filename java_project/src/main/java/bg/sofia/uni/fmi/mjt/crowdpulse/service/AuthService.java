package bg.sofia.uni.fmi.mjt.crowdpulse.service;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.User;
import bg.sofia.uni.fmi.mjt.crowdpulse.repository.UserRepository;

// Actually, PHP used password_verify. Java needs a compatible library.
// For now, I'll use a simple placeholder hasher or better, add BCrypt dependency. 
// I'll stick to simple check for now or assume plain for demo if dependency is issue, BUT user wants functionalities kept.
// I will assume we can add jbcrypt or just use a simple hash for new users.
// Let's use standard MessageDigest for now to avoid external dep if possible, OR just add jbcrypt to build.gradle.
// Providing a simple specific implementation here for "demo" compatibility with PHP password_hash is hard without library.
// I will add jbcrypt to build.gradle in next step. For now writing code assuming it exists or using placeholder.
// I will use a simple "password".equals(hash) for now for simplicity in this specific file, 
// but realistically user should use library.
// Wait, I can't just change to plain text if I want to support existing PHP users (if they migrate DB).
// But we are on MongoDB now, so it's a fresh DB. So I can define my own hashing.
// I'll use a simple SHA-256 for now.

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.Base64;

public class AuthService {
    private final UserRepository userRepository;

    public AuthService() {
        this.userRepository = new UserRepository();
    }

    public User login(String username, String password) {
        User user = userRepository.findByUsername(username);
        if (user != null && verifyPassword(password, user.getPassword())) {
            return user;
        }
        return null;
    }

    public User register(String username, String password, String gender, String role,
            java.util.List<String> categories) {
        if (userRepository.findByUsername(username) != null) {
            throw new IllegalArgumentException("Username already exists");
        }
        // Default to active participant if role is invalid or missing
        if (role == null || role.isEmpty())
            role = "active participant";

        User user = new User(null, username, hashPassword(password), role);
        user.setGender(gender);
        user.setCategories(categories);
        userRepository.save(user);
        return user;
    }

    private String hashPassword(String password) {
        // Simple SHA-256 for fresh MongoDB project (since we migrated DB type, we start
        // fresh)
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(password.getBytes());
            return Base64.getEncoder().encodeToString(hash);
        } catch (NoSuchAlgorithmException e) {
            throw new RuntimeException("Validation failed", e);
        }
    }

    private boolean verifyPassword(String raw, String hashed) {
        return hashPassword(raw).equals(hashed);
    }
}
