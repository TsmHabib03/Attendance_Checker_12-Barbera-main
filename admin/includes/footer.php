            </div>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
</body>
</html>

<style>
    .admin-footer {
        background: #2c3e50;
        color: white;
        padding: 1.5rem 0;
        margin-top: auto;
    }
    
    .admin-footer .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }
    
    .footer-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .footer-content p {
        margin: 0;
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    @media (max-width: 768px) {
        .admin-footer .container {
            padding: 0 1rem;
        }
        
        .footer-content {
            flex-direction: column;
            text-align: center;
        }
    }
</style>
